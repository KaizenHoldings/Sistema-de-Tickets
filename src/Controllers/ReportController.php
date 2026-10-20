<?php
namespace Controllers;

use Config\Database;
use PDO;
use Exception;

/**
 * Controlador de Reportes y Analíticas (ReportController)
 * Se encarga de procesar las métricas para el dashboard y la exportación de datos.
 * Aplica el principio de Responsabilidad Única (SRP) y Seguridad a Nivel de Filas (RLS).
 */
class ReportController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * 1. PREVENCIÓN DE FALLOS Y LIMPIEZA: Helper Centralizado de Seguridad RLS
     * Evita la duplicidad de código (DRY) y asegura que las reglas de negocio
     * de filtrado (Superadmin, Manager, Colaborador) sean idénticas en todas las consultas.
     *
     * @param array $currentUser Datos del usuario autenticado en sesión
     * @param int $areaFilter Filtro de área explícito (solo usado por superadmins)
     * @return array Retorna la cláusula WHERE y los parámetros PDO asociados
     */
    private function getRoleBasedAccessControlClause(array $currentUser, int $areaFilter = 0): array {
        $role = $currentUser['role_name'] ?? 'user';
        $userAreaId = (int)($currentUser['area_id'] ?? 0);
        $userId = (int)($currentUser['id'] ?? 0);
        
        $whereClause = "";
        $params = [];

        if ($role === 'admin') {
            // El Superadministrador puede ver todo, o filtrar por un área específica
            if ($areaFilter > 0) {
                $whereClause = " WHERE t.area_id = :area_id";
                $params[':area_id'] = $areaFilter;
            }
        } elseif ($role === 'manager') {
            // El Coordinador está restringido a ver solo los tickets de su propia área (Departamento)
            $whereClause = " WHERE t.area_id = :area_id";
            $params[':area_id'] = $userAreaId;
        } else {
            // Colaborador/Usuario común: Ve los tickets de su área, los que solicitó o los que atiende
            if ($userAreaId > 0) {
                $whereClause = " WHERE (t.area_id = :area_id OR t.requester_id = :user_id_req OR t.assigned_agent_id = :user_id_ag)";
                $params[':area_id'] = $userAreaId;
                $params[':user_id_req'] = $userId;
                $params[':user_id_ag'] = $userId;
            } else {
                $whereClause = " WHERE (t.requester_id = :user_id_req OR t.assigned_agent_id = :user_id_ag)";
                $params[':user_id_req'] = $userId;
                $params[':user_id_ag'] = $userId;
            }
        }

        return ['where' => $whereClause, 'params' => $params];
    }

    /**
     * Dashboard en tiempo real y KPIs de Servicio
     * @param array $currentUser Usuario autenticado
     * @param int $areaFilter Filtro opcional de departamento
     */
    public function getDashboardMetrics(array $currentUser, int $areaFilter = 0): array {
        try {
            // Obtener cláusula RLS segura y centralizada
            $rls = $this->getRoleBasedAccessControlClause($currentUser, $areaFilter);
            $whereClause = $rls['where'];
            $params = $rls['params'];

            // OPTIMIZACIÓN DE RENDIMIENTO: 
            // Todas las métricas clave (totales) se resuelven en un único Query 
            // mediante condicionales CASE WHEN, ahorrando múltiples viajes a la BD.
            $stmtTot = $this->db->prepare("
                SELECT 
                    COUNT(t.id) as total_tickets,
                    SUM(CASE WHEN t.status_id != 4 THEN 1 ELSE 0 END) as unresolved_tickets,
                    SUM(CASE WHEN t.is_sla_breached = 1 THEN 1 ELSE 0 END) as overdue_tickets,
                    SUM(CASE WHEN DATE(t.created_at) = CURDATE() AND t.status_id != 4 THEN 1 ELSE 0 END) as due_today_tickets,
                    SUM(CASE WHEN t.status_id = 2 THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN t.status_id = 3 THEN 1 ELSE 0 END) as waiting_customer_tickets,
                    SUM(CASE WHEN t.assigned_agent_id IS NULL AND t.status_id != 4 THEN 1 ELSE 0 END) as unassigned_tickets,
                    SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN t.priority = 'urgent' AND t.status_id != 4 THEN 1 ELSE 0 END) as prio_urgent,
                    SUM(CASE WHEN t.priority = 'high' AND t.status_id != 4 THEN 1 ELSE 0 END) as prio_high,
                    SUM(CASE WHEN t.priority = 'medium' AND t.status_id != 4 THEN 1 ELSE 0 END) as prio_medium,
                    SUM(CASE WHEN t.priority = 'low' AND t.status_id != 4 THEN 1 ELSE 0 END) as prio_low
                FROM tickets t
                $whereClause
            ");
            $stmtTot->execute($params);
            $totals = $stmtTot->fetch(PDO::FETCH_ASSOC) ?: [];

            // Conversión estricta a enteros para evitar problemas de tipos en el frontend
            $total = (int)($totals['total_tickets'] ?? 0);
            $resolved = (int)($totals['resolved_tickets'] ?? 0);
            $overdue = (int)($totals['overdue_tickets'] ?? 0);
            $unresolved = (int)($totals['unresolved_tickets'] ?? 0);
            $dueToday = max((int)($totals['due_today_tickets'] ?? 0), (int)($totals['prio_urgent'] ?? 0));
            $openTickets = (int)($totals['open_tickets'] ?? 0);
            $waitingCustomer = (int)($totals['waiting_customer_tickets'] ?? 0);
            $unassigned = (int)($totals['unassigned_tickets'] ?? 0);
            
            // Cálculo defensivo del SLA Rate evitando división por cero
            $slaRate = ($total > 0) ? round((($total - $overdue) / $total) * 100, 1) : 100;

            // 2. Carga de Trabajo por Agente
            $role = $currentUser['role_name'] ?? 'user';
            $sqlAgent = "
                SELECT u.name as agent_name, COUNT(t.id) as assigned_tickets
                FROM users u
                LEFT JOIN tickets t ON u.id = t.assigned_agent_id AND t.status_id IN (1,2,3)
                WHERE u.role_id IN (2, 3)
            ";
            $agentParams = [];
            $targetWorkloadArea = ($role === 'admin' && $areaFilter > 0) ? $areaFilter : (($role === 'manager') ? (int)$currentUser['area_id'] : 0);
            
            if ($targetWorkloadArea > 0) {
                $sqlAgent .= " AND u.area_id = :area_id";
                $agentParams[':area_id'] = $targetWorkloadArea;
            }
            $sqlAgent .= " GROUP BY u.id, u.name ORDER BY assigned_tickets DESC";

            $stmtAgent = $this->db->prepare($sqlAgent);
            $stmtAgent->execute($agentParams);
            $agentWorkload = $stmtAgent->fetchAll(PDO::FETCH_ASSOC);

            // 3. CORRECCIÓN DE SEGURIDAD (Information Disclosure)
            // Anteriormente, el manager podía ver la distribución de tickets de TODAS las áreas.
            // Ahora aplicamos la misma cláusula RLS pero adaptada para el join.
            $sqlArea = "
                SELECT a.name as area_name, COUNT(t.id) as total_tickets
                FROM areas a
                LEFT JOIN tickets t ON a.id = t.area_id 
            ";
            // Adaptamos el WHERE a un formato seguro para el LEFT JOIN
            if (!empty($whereClause)) {
                $joinClause = str_replace("WHERE", "AND", $whereClause);
                $sqlArea .= " " . $joinClause;
            }
            $sqlArea .= " GROUP BY a.id, a.name";

            $stmtArea = $this->db->prepare($sqlArea);
            $stmtArea->execute($params);
            $areaDistribution = $stmtArea->fetchAll(PDO::FETCH_ASSOC);

            // 4. Tickets Recientes (Limitado a 6 para optimizar UI y Memoria)
            $sqlRecent = "
                SELECT t.id, t.code, t.title, t.priority, t.created_at,
                       ks.name as status_name, ks.slug as status_slug,
                       u_req.name as requester_name,
                       u_ag.name as agent_name
                FROM tickets t
                INNER JOIN kanban_status ks ON t.status_id = ks.id
                INNER JOIN users u_req ON t.requester_id = u_req.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                $whereClause
                ORDER BY t.created_at DESC
                LIMIT 6
            ";
            $stmtRecent = $this->db->prepare($sqlRecent);
            $stmtRecent->execute($params);
            $recentTickets = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'kpis' => [
                    'total_tickets' => $total,
                    'unresolved_tickets' => $unresolved,
                    'overdue_tickets' => $overdue,
                    'due_today_tickets' => $dueToday,
                    'open_tickets' => $openTickets,
                    'waiting_customer_tickets' => $waitingCustomer,
                    'unassigned_tickets' => $unassigned,
                    'resolved_tickets' => $resolved,
                    'sla_compliance_rate' => $slaRate . '%',
                    'priorities' => [
                        'urgent' => (int)($totals['prio_urgent'] ?? 0),
                        'high' => (int)($totals['prio_high'] ?? 0),
                        'medium' => (int)($totals['prio_medium'] ?? 0),
                        'low' => (int)($totals['prio_low'] ?? 0),
                    ]
                ],
                'recent_tickets' => $recentTickets,
                'agent_workload' => $agentWorkload,
                'area_distribution' => $areaDistribution
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al generar métricas: ' . $e->getMessage()];
        }
    }

    /**
     * Exportación de Data Cruda a Formato CSV (Excel Compatible)
     * Utiliza la misma función RLS para asegurar que un usuario 
     * no exporte datos no autorizados.
     */
    public function exportTicketsCSV(array $currentUser): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_tickets_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        // BOM para asegurar que Excel lea correctamente los acentos UTF-8
        fputs($output, "\xEF\xBB\xBF");

        // Encabezados del reporte
        fputcsv($output, [
            'Código', 'Título', 'Departamento', 'Prioridad', 
            'Estado', 'Solicitante', 'Agente Asignado', 
            'Origen', 'Incumplió SLA', 'Fecha Creación'
        ]);

        // Reutilización (DRY) del helper de acceso
        $rls = $this->getRoleBasedAccessControlClause($currentUser);
        $whereClause = $rls['where'];
        $params = $rls['params'];

        // Se usa FETCH_ASSOC y escritura en el mismo bucle para optimizar consumo de RAM (Streaming Mode)
        $stmt = $this->db->prepare("
            SELECT t.code, t.title, a.name as area_name, t.priority, ks.name as status_name,
                   u_req.name as requester_name,
                   COALESCE(u_ag.name, 'Sin Asignar') as agent_name,
                   'Portal Web' as source,
                   CASE WHEN t.is_sla_breached = 1 THEN 'SÍ' ELSE 'NO' END as sla_breached,
                   t.created_at
            FROM tickets t
            INNER JOIN areas a ON t.area_id = a.id
            INNER JOIN kanban_status ks ON t.status_id = ks.id
            INNER JOIN users u_req ON t.requester_id = u_req.id
            LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
            $whereClause
            ORDER BY t.created_at DESC
        ");
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}

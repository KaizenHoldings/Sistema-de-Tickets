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

            // 2. Carga de Trabajo por Agente (Basado en la columna responsable de tickets)
            $whereAg = !empty($whereClause) ? $whereClause : "WHERE 1=1";
            $sqlAgent = "
                SELECT COALESCE(NULLIF(TRIM(t.responsable), ''), 'Sin Asignar') as agent_name,
                       COUNT(t.id) as total_tickets,
                       SUM(CASE WHEN t.status_id IN (1,2,3) THEN 1 ELSE 0 END) as assigned_tickets,
                       SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved_tickets
                FROM tickets t
                $whereAg
                GROUP BY agent_name
                HAVING agent_name != 'Sin Asignar' OR assigned_tickets > 0
                ORDER BY assigned_tickets DESC, total_tickets DESC
                LIMIT 10
            ";
            $stmtAgent = $this->db->prepare($sqlAgent);
            $stmtAgent->execute($params);
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
    public function exportTicketsCSV(array $currentUser, string $areaCode = ''): void {
        header('Content-Type: text/csv; charset=utf-8');
        $areaCode = strtoupper(trim($areaCode));
        $suffix = (!empty($areaCode) && in_array($areaCode, ['SSGG', 'IT'], true)) ? '_' . strtolower($areaCode) : '';
        header('Content-Disposition: attachment; filename=reporte_tickets' . $suffix . '_' . date('Y-m-d') . '.csv');

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

        if ($areaCode === 'SSGG') {
            $whereClause .= empty($whereClause) ? " WHERE (t.area_code = 'SSGG' OR t.area_id = 3)" : " AND (t.area_code = 'SSGG' OR t.area_id = 3)";
        } elseif ($areaCode === 'IT') {
            $whereClause .= empty($whereClause) ? " WHERE (t.area_code = 'IT' OR t.area_id IN (1, 2))" : " AND (t.area_code = 'IT' OR t.area_id IN (1, 2))";
        }

        // Se usa FETCH_ASSOC y escritura en el mismo bucle para optimizar consumo de RAM (Streaming Mode)
        $stmt = $this->db->prepare("
            SELECT t.code, t.title, 
                   COALESCE(NULLIF(TRIM(t.area_code), ''), a.name) as area_name, 
                   t.priority, ks.name as status_name,
                   COALESCE(NULLIF(TRIM(t.company_name), ''), u_req.name, 'Usuario') as requester_name,
                   COALESCE(NULLIF(TRIM(t.responsable), ''), u_ag.name, 'Sin Asignar') as agent_name,
                   CASE WHEN t.sharepoint_id IS NOT NULL THEN 'SharePoint' ELSE 'Portal Web' END as source,
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

    /**
     * Informes y Analíticas Avanzadas para el Dashboard de Reportes
     * Soporta filtrado por período y por agente (columna responsable).
     *
     * @param array $currentUser Usuario autenticado
     * @param array $filterOptions Opciones de filtrado ['period' => 'all'|'30days'|'month'|'quarter'|'year', 'responsable' => string, 'area_id' => int]
     * @return array
     */
    public function getReportsAnalytics(array $currentUser, array $filterOptions = []): array {
        try {
            $areaFilter = (int)($filterOptions['area_id'] ?? 0);
            $rls = $this->getRoleBasedAccessControlClause($currentUser, $areaFilter);
            $whereClause = $rls['where'];
            $params = $rls['params'];

            // Filtro por Período
            $period = $filterOptions['period'] ?? 'all';
            $dateCondition = "";
            if ($period === '30days') {
                $dateCondition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            } elseif ($period === 'month') {
                $dateCondition = "t.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
            } elseif ($period === 'quarter') {
                $dateCondition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            } elseif ($period === 'year') {
                $dateCondition = "t.created_at >= DATE_FORMAT(NOW(), '%Y-01-01 00:00:00')";
            }

            if (!empty($dateCondition)) {
                $whereClause .= empty($whereClause) ? " WHERE $dateCondition" : " AND $dateCondition";
            }

            // Filtro por Área / Departamento (SSGG vs IT)
            $selectedArea = strtoupper(trim($filterOptions['area'] ?? $filterOptions['area_code'] ?? 'all'));
            if ($selectedArea === 'SSGG') {
                $whereClause .= empty($whereClause) ? " WHERE (t.area_code = 'SSGG' OR t.area_id = 3)" : " AND (t.area_code = 'SSGG' OR t.area_id = 3)";
            } elseif ($selectedArea === 'IT') {
                $whereClause .= empty($whereClause) ? " WHERE (t.area_code = 'IT' OR t.area_id IN (1, 2))" : " AND (t.area_code = 'IT' OR t.area_id IN (1, 2))";
            }

            // Filtro por Agente (Columna Responsable)
            $selectedResp = trim($filterOptions['responsable'] ?? '');
            if (!empty($selectedResp)) {
                if ($selectedResp === 'Sin Asignar') {
                    $whereClause .= empty($whereClause) ? " WHERE (t.responsable IS NULL OR TRIM(t.responsable) = '')" : " AND (t.responsable IS NULL OR TRIM(t.responsable) = '')";
                } else {
                    $whereClause .= empty($whereClause) ? " WHERE TRIM(t.responsable) = :filter_resp" : " AND TRIM(t.responsable) = :filter_resp";
                    $params[':filter_resp'] = $selectedResp;
                }
            }

            // 1. Métricas / KPIs Generales
            $stmtKpi = $this->db->prepare("
                SELECT 
                    COUNT(t.id) as total_tickets,
                    SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN t.status_id != 4 THEN 1 ELSE 0 END) as unresolved_tickets,
                    SUM(CASE WHEN t.is_sla_breached = 1 THEN 1 ELSE 0 END) as overdue_tickets,
                    AVG(CASE WHEN t.status_id = 4 AND t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) ELSE NULL END) as avg_resolution_hours
                FROM tickets t
                $whereClause
            ");
            $stmtKpi->execute($params);
            $kpiRow = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

            $total = (int)($kpiRow['total_tickets'] ?? 0);
            $resolved = (int)($kpiRow['resolved_tickets'] ?? 0);
            $unresolved = (int)($kpiRow['unresolved_tickets'] ?? 0);
            $overdue = (int)($kpiRow['overdue_tickets'] ?? 0);
            $rawAvgHours = (float)($kpiRow['avg_resolution_hours'] ?? 0);
            
            $resRate = $total > 0 ? round(($resolved / $total) * 100, 1) : 100;
            $slaRate = $resolved > 0 ? round((($resolved - $overdue) / $resolved) * 100, 1) : 100;
            if ($slaRate < 0) $slaRate = 0;

            // Formato amigable de tiempo medio
            if ($rawAvgHours <= 0) {
                $avgTimeStr = '< 1 hora';
            } elseif ($rawAvgHours < 24) {
                $avgTimeStr = round($rawAvgHours, 1) . ' horas';
            } else {
                $days = round($rawAvgHours / 24, 1);
                $avgTimeStr = $days . ' ' . ($days == 1 ? 'día' : 'días');
            }

            // 2. Evolución Temporal (Línea)
            $isShortPeriod = in_array($period, ['30days', 'month'], true);
            $groupByFormat = $isShortPeriod ? '%d/%m' : '%Y-%m';
            
            $stmtLine = $this->db->prepare("
                SELECT DATE_FORMAT(t.created_at, '$groupByFormat') as period_key,
                       COUNT(t.id) as created_count,
                       SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved_count
                FROM tickets t
                $whereClause
                " . (empty($whereClause) ? "WHERE" : "AND") . " t.created_at IS NOT NULL
                GROUP BY period_key
                ORDER BY MIN(t.created_at) ASC
            ");
            $stmtLine->execute($params);
            $lineRows = $stmtLine->fetchAll(PDO::FETCH_ASSOC);

            $monthNames = [
                '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
                '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
                '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
            ];

            $chartLineLabels = [];
            $chartLineCreated = [];
            $chartLineResolved = [];
            foreach ($lineRows as $r) {
                $label = $r['period_key'];
                if (!$isShortPeriod && strpos($label, '-') !== false) {
                    [$y, $m] = explode('-', $label);
                    $label = ($monthNames[$m] ?? $m) . ' ' . substr($y, 2);
                }
                $chartLineLabels[] = $label;
                $chartLineCreated[] = (int)$r['created_count'];
                $chartLineResolved[] = (int)$r['resolved_count'];
            }

            // 3. Tickets por Agente (COLUMNA RESPONSABLE)
            $stmtBar = $this->db->prepare("
                SELECT COALESCE(NULLIF(TRIM(t.responsable), ''), 'Sin Asignar') as agent_name,
                       COUNT(t.id) as total_tickets,
                       SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved_tickets
                FROM tickets t
                $whereClause
                GROUP BY agent_name
                ORDER BY resolved_tickets DESC, total_tickets DESC
                LIMIT 10
            ");
            $stmtBar->execute($params);
            $barRows = $stmtBar->fetchAll(PDO::FETCH_ASSOC);

            $chartBarLabels = [];
            $chartBarData = [];
            $topAgent = 'N/A';
            if (!empty($barRows)) {
                $topAgent = $barRows[0]['agent_name'];
            }
            foreach ($barRows as $br) {
                $chartBarLabels[] = $br['agent_name'];
                $chartBarData[] = (int)$br['resolved_tickets'];
            }

            // 4. Distribución por Prioridad (Dona)
            $stmtPie = $this->db->prepare("
                SELECT t.priority, COUNT(t.id) as cnt
                FROM tickets t
                $whereClause
                GROUP BY t.priority
            ");
            $stmtPie->execute($params);
            $pieRows = $stmtPie->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $chartPieLabels = ['Crítica / Urgente', 'Alta', 'Media', 'Baja'];
            $chartPieData = [
                (int)($pieRows['urgent'] ?? 0),
                (int)($pieRows['high'] ?? 0),
                (int)($pieRows['medium'] ?? 0),
                (int)($pieRows['low'] ?? 0)
            ];

            // 5. Histograma de Tiempo de Resolución
            $stmtHist = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) < 4 THEN 1 ELSE 0 END) as b_under_4h,
                    SUM(CASE WHEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) BETWEEN 4 AND 24 THEN 1 ELSE 0 END) as b_4_24h,
                    SUM(CASE WHEN TIMESTAMPDIFF(DAY, t.created_at, t.resolved_at) BETWEEN 1 AND 3 THEN 1 ELSE 0 END) as b_1_3d,
                    SUM(CASE WHEN TIMESTAMPDIFF(DAY, t.created_at, t.resolved_at) BETWEEN 3 AND 7 THEN 1 ELSE 0 END) as b_3_7d,
                    SUM(CASE WHEN TIMESTAMPDIFF(DAY, t.created_at, t.resolved_at) > 7 THEN 1 ELSE 0 END) as b_over_7d
                FROM tickets t
                $whereClause
                " . (empty($whereClause) ? "WHERE" : "AND") . " t.status_id = 4 AND t.resolved_at IS NOT NULL
            ");
            $stmtHist->execute($params);
            $histRow = $stmtHist->fetch(PDO::FETCH_ASSOC) ?: [];

            $chartHistLabels = ['< 4h', '4 - 24h', '1 - 3 días', '3 - 7 días', '> 7 días'];
            $chartHistData = [
                (int)($histRow['b_under_4h'] ?? 0),
                (int)($histRow['b_4_24h'] ?? 0),
                (int)($histRow['b_1_3d'] ?? 0),
                (int)($histRow['b_3_7d'] ?? 0),
                (int)($histRow['b_over_7d'] ?? 0)
            ];

            // 6. Top Empresas Solicitantes (Scatter/Bar)
            $stmtComp = $this->db->prepare("
                SELECT COALESCE(NULLIF(TRIM(t.company_name), ''), 'Sin Empresa') as company,
                       COUNT(t.id) as cnt
                FROM tickets t
                $whereClause
                GROUP BY company
                ORDER BY cnt DESC
                LIMIT 6
            ");
            $stmtComp->execute($params);
            $compRows = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

            $chartCompLabels = [];
            $chartCompData = [];
            foreach ($compRows as $cr) {
                $chartCompLabels[] = $cr['company'];
                $chartCompData[] = (int)$cr['cnt'];
            }

            // 7. Distribución por Área o Estatus según el Filtro
            $chartAreaLabels = [];
            $chartAreaData = [];
            $chartBoxTitle = 'Solicitudes por Área (SSGG vs IT)';

            if ($selectedArea === 'SSGG' || $selectedArea === 'IT') {
                $chartBoxTitle = 'Estatus de Solicitudes (' . $selectedArea . ')';
                $stmtAreaStat = $this->db->prepare("
                    SELECT ks.name as status_name, COUNT(t.id) as cnt
                    FROM tickets t
                    INNER JOIN kanban_status ks ON t.status_id = ks.id
                    $whereClause
                    GROUP BY ks.id, ks.name
                    ORDER BY cnt DESC
                ");
                $stmtAreaStat->execute($params);
                $statRows = $stmtAreaStat->fetchAll(PDO::FETCH_ASSOC);
                foreach ($statRows as $sr) {
                    $chartAreaLabels[] = $sr['status_name'];
                    $chartAreaData[] = (int)$sr['cnt'];
                }
            } else {
                $stmtArea = $this->db->prepare("
                    SELECT 
                        CASE 
                            WHEN t.area_code = 'SSGG' OR t.area_id = 3 THEN 'SSGG (Servicios Generales)'
                            ELSE 'IT (Tecnología e Informática)'
                        END as area_name,
                        COUNT(t.id) as cnt
                    FROM tickets t
                    $whereClause
                    GROUP BY area_name
                    ORDER BY cnt DESC
                ");
                $stmtArea->execute($params);
                $areaRows = $stmtArea->fetchAll(PDO::FETCH_ASSOC);
                foreach ($areaRows as $ar) {
                    $chartAreaLabels[] = $ar['area_name'];
                    $chartAreaData[] = (int)$ar['cnt'];
                }
            }

            // 8. Lista de Responsables para el dropdown de filtro (Filtrado por Área si aplica)
            $respAreaCond = "";
            if ($selectedArea === 'SSGG') {
                $respAreaCond = " AND (area_code = 'SSGG' OR area_id = 3)";
            } elseif ($selectedArea === 'IT') {
                $respAreaCond = " AND (area_code = 'IT' OR area_id IN (1, 2))";
            }
            $stmtAllResp = $this->db->query("
                SELECT DISTINCT TRIM(responsable) as resp
                FROM tickets
                WHERE responsable IS NOT NULL AND TRIM(responsable) != '' $respAreaCond
                ORDER BY resp ASC
            ");
            $responsablesList = $stmtAllResp->fetchAll(PDO::FETCH_COLUMN);

            // 9. Resumen Comparativo de Departamentos (SSGG vs IT)
            $periodWhere = !empty($dateCondition) ? "WHERE $dateCondition" : "";
            $stmtDeptComp = $this->db->query("
                SELECT 
                    CASE 
                        WHEN t.area_code = 'SSGG' OR t.area_id = 3 THEN 'SSGG'
                        ELSE 'IT'
                    END as dept_code,
                    COUNT(t.id) as total,
                    SUM(CASE WHEN t.status_id = 4 THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN t.status_id != 4 THEN 1 ELSE 0 END) as unresolved,
                    AVG(CASE WHEN t.status_id = 4 AND t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) ELSE NULL END) as avg_hours
                FROM tickets t
                $periodWhere
                GROUP BY dept_code
            ");
            $deptRows = $stmtDeptComp->fetchAll(PDO::FETCH_ASSOC);
            $deptStats = [];
            foreach ($deptRows as $dr) {
                $c = $dr['dept_code'];
                $dTotal = (int)$dr['total'];
                $dRes = (int)$dr['resolved'];
                $dUnres = (int)$dr['unresolved'];
                $dRate = $dTotal > 0 ? round(($dRes / $dTotal) * 100, 1) . '%' : '100%';
                $dAvgH = (float)($dr['avg_hours'] ?? 0);
                $dAvgStr = $dAvgH <= 0 ? '< 1h' : ($dAvgH < 24 ? round($dAvgH, 1) . 'h' : round($dAvgH / 24, 1) . ' días');

                $deptStats[$c] = [
                    'code' => $c,
                    'name' => ($c === 'SSGG') ? 'Servicios Generales (SSGG)' : 'Tecnología e Informática (IT)',
                    'total' => $dTotal,
                    'resolved' => $dRes,
                    'unresolved' => $dUnres,
                    'resolution_rate' => $dRate,
                    'avg_resolution_hours' => $dAvgStr
                ];
            }

            // Top Agente por Departamento
            $stmtTopSSGG = $this->db->query("
                SELECT TRIM(responsable) as agent_name, COUNT(*) as cnt
                FROM tickets
                WHERE (area_code = 'SSGG' OR area_id = 3) AND status_id = 4 AND responsable IS NOT NULL AND TRIM(responsable) != ''
                GROUP BY agent_name ORDER BY cnt DESC LIMIT 1
            ");
            $deptStats['SSGG']['top_agent'] = $stmtTopSSGG->fetch(PDO::FETCH_ASSOC)['agent_name'] ?? 'N/A';

            $stmtTopIT = $this->db->query("
                SELECT TRIM(responsable) as agent_name, COUNT(*) as cnt
                FROM tickets
                WHERE (area_code = 'IT' OR area_id IN (1, 2)) AND status_id = 4 AND responsable IS NOT NULL AND TRIM(responsable) != ''
                GROUP BY agent_name ORDER BY cnt DESC LIMIT 1
            ");
            $deptStats['IT']['top_agent'] = $stmtTopIT->fetch(PDO::FETCH_ASSOC)['agent_name'] ?? 'N/A';

            return [
                'success' => true,
                'selected_area' => $selectedArea,
                'kpis' => [
                    'total' => $total,
                    'resolved' => $resolved,
                    'unresolved' => $unresolved,
                    'resolution_rate' => $resRate . '%',
                    'avg_resolution_hours' => $avgTimeStr,
                    'sla_rate' => $slaRate . '%',
                    'top_agent' => $topAgent
                ],
                'departments' => $deptStats,
                'charts' => [
                    'chartLine' => [
                        'labels' => $chartLineLabels,
                        'data' => $chartLineCreated,
                        'resolved' => $chartLineResolved,
                        'title' => 'Evolución Temporal de Solicitudes (' . ($selectedArea === 'all' ? 'Todas las Áreas' : $selectedArea) . ')'
                    ],
                    'chartBar' => [
                        'labels' => $chartBarLabels,
                        'data' => $chartBarData,
                        'title' => 'Tickets Resueltos por Agente - ' . ($selectedArea === 'all' ? 'General' : $selectedArea)
                    ],
                    'chartPie' => [
                        'labels' => $chartPieLabels,
                        'data' => $chartPieData,
                        'title' => 'Distribución por Prioridad'
                    ],
                    'chartHist' => [
                        'labels' => $chartHistLabels,
                        'data' => $chartHistData,
                        'title' => 'Distribución de Tiempos de Resolución'
                    ],
                    'chartScatter' => [
                        'labels' => $chartCompLabels,
                        'data' => $chartCompData,
                        'title' => 'Top Empresas con Más Solicitudes'
                    ],
                    'chartBoxplot' => [
                        'labels' => $chartAreaLabels,
                        'data' => $chartAreaData,
                        'title' => $chartBoxTitle
                    ]
                ],
                'responsables' => $responsablesList
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al calcular analíticas: ' . $e->getMessage()
            ];
        }
    }
}

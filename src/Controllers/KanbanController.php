<?php
namespace Controllers;

use Config\Database;
use PDO;
use Exception;

class KanbanController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtener el tablero Kanban aplicando el MODELO DE 3 ROLES UNIFICADO:
     * 1. Superadmin ('admin'): Ve todos los tickets de la empresa o filtrados por área.
     * 2. Coordinador de Área ('manager'): Ve la totalidad de tickets de su departamento para gestionarlos y asignarlos.
     * 3. Usuario / Colaborador ('user' / 'agent' / 'requester'): Ve los tickets que ha CREADO y los tickets que le han sido ASIGNADOS.
     */
    public function getBoardData(int $areaId, array $currentUser): array {
        try {
            $stmtStatus = $this->db->query("SELECT id, name, slug, sort_order FROM kanban_status ORDER BY sort_order ASC");
            $columns = $stmtStatus->fetchAll();

            if (empty($columns)) {
                $columns = [
                    ['id' => 1, 'name' => 'Por Asignar', 'slug' => 'unassigned', 'sort_order' => 1],
                    ['id' => 2, 'name' => 'En Progreso', 'slug' => 'in_progress', 'sort_order' => 2],
                    ['id' => 3, 'name' => 'Pendiente Usuario', 'slug' => 'pending_user', 'sort_order' => 3],
                    ['id' => 4, 'name' => 'Resuelto', 'slug' => 'resolved', 'sort_order' => 4]
                ];
            }

            $role = $currentUser['role_name'] ?? 'user';
            $userId = (int)($currentUser['id'] ?? 0);
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $sql = "
                SELECT t.id, t.code, t.title, t.priority, t.status_id, t.area_id,
                       u_req.name as requester_name,
                       u_ag.name as agent_name,
                       t.created_at
                FROM tickets t
                INNER JOIN users u_req ON t.requester_id = u_req.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                WHERE 1=1
            ";

            $params = [];

            if ($role === 'admin') {
                if ($areaId > 0) {
                    $sql .= " AND t.area_id = :area_id";
                    $params[':area_id'] = $areaId;
                }
            } elseif ($role === 'manager') {
                // Coordinador ve su departamento completo
                $sql .= " AND t.area_id = :user_area_id";
                $params[':user_area_id'] = $userAreaId;
            } else {
                // Colaborador: Crea tickets, consulta sus tickets, atiende asignados (de su área y especialmente los suyos propios)
                if ($userAreaId > 0) {
                    $sql .= " AND (t.area_id = :user_area_id OR t.requester_id = :user_id_req OR t.assigned_agent_id = :user_id_ag)";
                    $params[':user_area_id'] = $userAreaId;
                    $params[':user_id_req'] = $userId;
                    $params[':user_id_ag'] = $userId;
                } else {
                    $sql .= " AND (t.requester_id = :user_id_req OR t.assigned_agent_id = :user_id_ag)";
                    $params[':user_id_req'] = $userId;
                    $params[':user_id_ag'] = $userId;
                }
            }

            $sql .= " ORDER BY t.created_at DESC";

            $stmtTickets = $this->db->prepare($sql);
            $stmtTickets->execute($params);
            $tickets = $stmtTickets->fetchAll();

            $board = [];
            foreach ($columns as $col) {
                $board[$col['id']] = [
                    'id' => $col['id'],
                    'name' => $col['name'],
                    'slug' => $col['slug'],
                    'tickets' => []
                ];
            }

            foreach ($tickets as $t) {
                if (isset($board[$t['status_id']])) {
                    $board[$t['status_id']]['tickets'][] = $t;
                }
            }

            return ['success' => true, 'data' => array_values($board)];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    ['id' => 1, 'name' => 'Por Asignar', 'slug' => 'unassigned', 'tickets' => []],
                    ['id' => 2, 'name' => 'En Progreso', 'slug' => 'in_progress', 'tickets' => []],
                    ['id' => 3, 'name' => 'Pendiente Usuario', 'slug' => 'pending_user', 'tickets' => []],
                    ['id' => 4, 'name' => 'Resuelto', 'slug' => 'resolved', 'tickets' => []]
                ]
            ];
        }
    }

    /**
     * Mover tarjeta Kanban / Cambiar estado con nota o comentario obligatorio
     */
    public function updateTicketStatus(int $ticketId, int $newStatusId, array $currentUser, string $comment = '', bool $isInternal = false): array {
        try {
            $comment = trim($comment);
            if (empty($comment)) {
                return ['success' => false, 'message' => 'Es obligatorio ingresar un comentario o nota interna explicando el cambio de estado.'];
            }

            $stmtTicket = $this->db->prepare("SELECT id, code, area_id, status_id, assigned_agent_id, requester_id FROM tickets WHERE id = :id");
            $stmtTicket->execute([':id' => $ticketId]);
            $ticket = $stmtTicket->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.'];
            }

            $role = $currentUser['role_name'] ?? '';
            $userId = (int)($currentUser['id'] ?? 0);
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            // Permisos: Admin, Manager del área o Agente asignado
            $isAdmin = ($role === 'admin');
            $isAreaManager = ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId);
            $isAssigned = ((int)$ticket['assigned_agent_id'] === $userId);

            if (!$isAdmin && !$isAreaManager && !$isAssigned) {
                return ['success' => false, 'message' => 'Solo agentes asignados o coordinadores pueden modificar el estado de este ticket.'];
            }

            $stmtStatus = $this->db->prepare("SELECT id, name, slug FROM kanban_status WHERE id = :id");
            $stmtStatus->execute([':id' => $newStatusId]);
            $status = $stmtStatus->fetch();

            if (!$status) {
                return ['success' => false, 'message' => 'Estado destino no válido.'];
            }

            $isCompleted = ($newStatusId === 4 || in_array($status['name'], ['Resuelto', 'Completado', 'Cerrado'])) ? 1 : 0;
            
            $this->db->beginTransaction();

            $stmtUpdate = $this->db->prepare("
                UPDATE tickets 
                SET status_id = :status_id,
                    resolved_at = CASE WHEN :is_completed = 1 THEN NOW() ELSE resolved_at END,
                    updated_at = NOW()
                WHERE id = :ticket_id
            ");
            $stmtUpdate->execute([
                ':status_id' => $newStatusId,
                ':is_completed' => $isCompleted,
                ':ticket_id' => $ticketId
            ]);

            // Registrar comentario explicativo en el historial
            $prefix = $isInternal ? "🔒 [CAMBIO A {$status['name']}]: " : "📢 [CAMBIO A {$status['name']}]: ";
            $stmtComment = $this->db->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal)
                VALUES (:ticket_id, :user_id, :comment, :is_internal)
            ");
            $stmtComment->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':comment' => $prefix . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
                ':is_internal' => $isInternal ? 1 : 0
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Ticket {$ticket['code']} actualizado a '{$status['name']}' con nota registrada.",
                'data' => [
                    'ticket_id' => $ticketId,
                    'status_id' => $newStatusId,
                    'status_name' => $status['name'],
                    'status_slug' => $status['slug'],
                    'is_resolved' => ($newStatusId === 4)
                ]
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'message' => 'Error al mover ticket: ' . $e->getMessage()];
        }
    }
}

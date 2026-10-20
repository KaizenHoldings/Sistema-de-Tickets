<?php
namespace Controllers;

use Config\Database;
use PDO;
use Exception;

class CommentController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtener el historial de comentarios con Validación de Privacidad e IDOR
     */
    public function getCommentsByTicket(int $ticketId, array $currentUser): array {
        try {
            // Verificar existencia y propiedad del ticket
            $stmtTicket = $this->db->prepare("SELECT id, requester_id, area_id, assigned_agent_id FROM tickets WHERE id = :id");
            $stmtTicket->execute([':id' => $ticketId]);
            $ticket = $stmtTicket->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            $role = $currentUser['role_name'];
            $userId = (int)$currentUser['id'];
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $isOwner = ((int)$ticket['requester_id'] === $userId);
            $isAssignedAgent = ((int)$ticket['assigned_agent_id'] === $userId);
            $isAreaManager = ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId);
            $isAreaStaff = in_array($role, ['colaborador', 'agent', 'user', 'manager'], true) && (int)$ticket['area_id'] === $userAreaId;
            $isAdmin = ($role === 'admin');

            if (!$isAdmin && !$isOwner && !$isAssignedAgent && !$isAreaManager && !$isAreaStaff) {
                return ['success' => false, 'message' => 'Acceso denegado a los comentarios del ticket.', 'code' => 403];
            }

            $canViewInternal = ($isAdmin || $isAreaManager || $isAssignedAgent || $isAreaStaff);
            
            $sql = "
                SELECT c.id, c.ticket_id, c.comment, c.is_internal, c.created_at,
                       u.name as user_name, r.name as role_name
                FROM ticket_comments c
                INNER JOIN users u ON c.user_id = u.id
                INNER JOIN roles r ON u.role_id = r.id
                WHERE c.ticket_id = :ticket_id
            ";

            // Usuarios solicitantes NUNCA pueden ver notas internas privadas
            if (!$canViewInternal) {
                $sql .= " AND c.is_internal = 0";
            }

            $sql .= " ORDER BY c.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':ticket_id' => $ticketId]);
            $comments = $stmt->fetchAll();

            // Escapar contenido HTML para evitar XSS almacenado
            foreach ($comments as &$c) {
                $c['comment'] = htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8');
                $c['user_name'] = htmlspecialchars($c['user_name'], ENT_QUOTES, 'UTF-8');
            }

            return ['success' => true, 'data' => $comments];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Agregar un comentario público o nota interna (con saneamiento XSS)
     */
    public function addComment(int $ticketId, string $comment, bool $isInternal, array $currentUser): array {
        try {
            $commentClean = htmlspecialchars(trim($comment), ENT_QUOTES, 'UTF-8');

            if (empty($commentClean)) {
                return ['success' => false, 'message' => 'El comentario no puede estar vacío.'];
            }

            $stmtTicket = $this->db->prepare("SELECT id, requester_id, area_id, assigned_agent_id FROM tickets WHERE id = :id");
            $stmtTicket->execute([':id' => $ticketId]);
            $ticket = $stmtTicket->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            $role = $currentUser['role_name'];
            $userId = (int)$currentUser['id'];
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            // Solo personal técnico, coordinadores o asignados pueden crear notas internas
            $isStaff = ($role === 'admin' || ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId) || ((int)$ticket['assigned_agent_id'] === $userId) || (in_array($role, ['colaborador', 'agent'], true) && (int)$ticket['area_id'] === $userAreaId));
            if ($isInternal && !$isStaff) {
                return ['success' => false, 'message' => 'Solo colaboradores asignados o coordinadores tienen permiso para crear notas privadas.'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal)
                VALUES (:ticket_id, :user_id, :comment, :is_internal)
            ");
            
            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':comment' => $commentClean,
                ':is_internal' => ($isInternal && $isStaff) ? 1 : 0
            ]);

            return [
                'success' => true,
                'message' => $isInternal ? 'Nota privada agregada exitosamente.' : 'Respuesta publicada exitosamente.'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al guardar comentario: ' . $e->getMessage()];
        }
    }
}

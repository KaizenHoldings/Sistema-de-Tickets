<?php
namespace Controllers;

use Config\Database;
use PDO;
use Exception;

class TicketController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Crear un nuevo ticket con validación estricta de subida de archivos (anti-RCE & OWASP File Upload Hardening)
     */
    public function createTicket(array $data, array $files, array $currentUser): array {
        try {
            $title = htmlspecialchars(trim($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars(trim($data['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $areaId = (int)($data['area_id'] ?? 0);
            $priority = $data['priority'] ?? 'medium';

            $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($priority, $allowedPriorities, true)) {
                $priority = 'medium';
            }

            if (empty($title) || empty($description) || $areaId <= 0) {
                return ['success' => false, 'message' => 'Título, descripción y área de destino son obligatorios.'];
            }

            // PROCESAMIENTO SEGURO DE ARCHIVOS (ANTI-MALWARE & OWASP AUDIT)
            $attachmentPath = null;
            if (isset($files['attachment']) && $files['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $files['attachment'];
                
                // 1. Validar Tamaño Máximo (5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    return ['success' => false, 'message' => 'El archivo supera el tamaño máximo permitido de 5MB.'];
                }

                // 2. Validar Extensión
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExtensions, true)) {
                    return ['success' => false, 'message' => 'Extensión no permitida. Solo imágenes (PNG, JPG, WEBP) o documentos PDF.'];
                }

                // 3. Validar MIME Type Real con finfo
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                    if (!in_array($mimeType, $allowedMimes, true)) {
                        return ['success' => false, 'message' => 'El contenido del archivo no coincide con un formato seguro válido.'];
                    }
                }

                // 4. Guardar con Nombre Aleatorizado
                $uploadDir = __DIR__ . '/../../public/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $randomName = 'ticket_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = $uploadDir . $randomName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $attachmentPath = 'uploads/' . $randomName;
                }
            }

            // Generar código único correlativo
            $stmtArea = $this->db->prepare("SELECT name FROM areas WHERE id = :id");
            $stmtArea->execute([':id' => $areaId]);
            $area = $stmtArea->fetch();
            
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $area['name'] ?? 'TSK'), 0, 2));
            $year = date('Y');
            
            $stmtCount = $this->db->prepare("SELECT COUNT(*) as total FROM tickets WHERE area_id = :area_id");
            $stmtCount->execute([':area_id' => $areaId]);
            $count = (int)$stmtCount->fetch()['total'] + 1;
            
            $code = sprintf("%s-%s-%03d", $prefix, $year, $count);

            $stmt = $this->db->prepare("
                INSERT INTO tickets (code, title, description, priority, status_id, area_id, requester_id, attachment_path)
                VALUES (:code, :title, :description, :priority, 1, :area_id, :requester_id, :attachment_path)
            ");

            $stmt->execute([
                ':code' => $code,
                ':title' => $title,
                ':description' => $description,
                ':priority' => $priority,
                ':area_id' => $areaId,
                ':requester_id' => $currentUser['id'],
                ':attachment_path' => $attachmentPath
            ]);

            $ticketId = $this->db->lastInsertId();

            return [
                'success' => true,
                'message' => "Ticket {$code} generado exitosamente" . ($attachmentPath ? " con archivo adjunto." : "."),
                'ticket_id' => $ticketId,
                'code' => $code
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al crear ticket: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener detalle completo de un ticket CON PROTECCIÓN IDOR ESTRICTA
     */
    public function getTicketDetail(int $ticketId, array $currentUser): array {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, 
                       ks.name as status_name, ks.slug as status_slug,
                       a.name as area_name, a.manager_id,
                       u_req.name as requester_name, u_req.email as requester_email,
                       u_ag.name as agent_name, u_ag.email as agent_email
                FROM tickets t
                INNER JOIN kanban_status ks ON t.status_id = ks.id
                INNER JOIN areas a ON t.area_id = a.id
                INNER JOIN users u_req ON t.requester_id = u_req.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                WHERE t.id = :id
            ");
            $stmt->execute([':id' => $ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            // CONTROLES DE SEGURIDAD IDOR (INSECURE DIRECT OBJECT REFERENCE):
            // 1. Superadmin ve cualquier ticket.
            // 2. Coordinador/Manager ve tickets de su área.
            // 3. Agente/Colaborador ve tickets asignados o unassigned de su área.
            // 4. Solicitante solo ve sus propios tickets.
            $role = $currentUser['role_name'];
            $userId = (int)$currentUser['id'];
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $isOwner = ((int)$ticket['requester_id'] === $userId);
            $isAssignedAgent = ((int)$ticket['assigned_agent_id'] === $userId);
            $isAreaManager = ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId);
            $isAreaStaff = in_array($role, ['colaborador', 'agent', 'user', 'manager'], true) && (int)$ticket['area_id'] === $userAreaId;
            $isAdmin = ($role === 'admin');

            if (!$isAdmin && !$isOwner && !$isAssignedAgent && !$isAreaManager && !$isAreaStaff) {
                return [
                    'success' => false, 
                    'message' => 'Acceso denegado (IDOR Protection): No tiene permisos para consultar este ticket.',
                    'code' => 403
                ];
            }

            // Consultar encuesta de satisfacción CSAT si existe
            $stmtSurvey = $this->db->prepare("SELECT rating, feedback, created_at FROM ticket_satisfaction_surveys WHERE ticket_id = :id");
            $stmtSurvey->execute([':id' => $ticketId]);
            $ticket['survey'] = $stmtSurvey->fetch() ?: null;

            return ['success' => true, 'data' => $ticket];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Registrar respuesta a Encuesta de Satisfacción (CSAT)
     */
    public function submitSatisfactionSurvey(int $ticketId, int $rating, ?string $feedback, array $currentUser): array {
        try {
            if ($rating < 1 || $rating > 5) {
                return ['success' => false, 'message' => 'La calificación debe estar entre 1 y 5 emojis/estrellas.'];
            }

            $stmtTicket = $this->db->prepare("SELECT id, requester_id, status_id FROM tickets WHERE id = :id");
            $stmtTicket->execute([':id' => $ticketId]);
            $ticket = $stmtTicket->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            if ((int)$ticket['status_id'] !== 4) {
                return ['success' => false, 'message' => 'Solo se pueden calificar tickets que hayan sido resueltos.'];
            }

            $userId = (int)$currentUser['id'];
            $role = $currentUser['role_name'];
            if ((int)$ticket['requester_id'] !== $userId && $role !== 'admin') {
                return ['success' => false, 'message' => 'Solo el solicitante del ticket puede completar la encuesta de satisfacción.', 'code' => 403];
            }

            $stmtCheck = $this->db->prepare("SELECT id FROM ticket_satisfaction_surveys WHERE ticket_id = :ticket_id");
            $stmtCheck->execute([':ticket_id' => $ticketId]);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'message' => 'Este ticket ya cuenta con una encuesta registrada previamente.'];
            }

            $stmtInsert = $this->db->prepare("
                INSERT INTO ticket_satisfaction_surveys (ticket_id, user_id, rating, feedback)
                VALUES (:ticket_id, :user_id, :rating, :feedback)
            ");
            $stmtInsert->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':rating' => $rating,
                ':feedback' => htmlspecialchars(trim($feedback ?? ''), ENT_QUOTES, 'UTF-8')
            ]);

            return ['success' => true, 'message' => '¡Muchas gracias! Su valoración del servicio ha sido guardada exitosamente.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lista de tickets por asignar (Control de Acceso por Área)
     */
    public function getUnassignedTickets(int $areaId, array $currentUser): array {
        try {
            $role = $currentUser['role_name'];
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $sql = "
                SELECT t.id, t.code, t.title, t.description, t.priority, t.created_at, t.attachment_path, t.area_id,
                       u_req.name as requester_name, u_req.email as requester_email,
                       a.name as area_name
                FROM tickets t
                INNER JOIN areas a ON t.area_id = a.id
                INNER JOIN users u_req ON t.requester_id = u_req.id
                WHERE t.assigned_agent_id IS NULL AND t.status_id != 4
            ";
            $params = [];

            if ($role === 'admin') {
                if ($areaId > 0) {
                    $sql .= " AND t.area_id = :area_id";
                    $params[':area_id'] = $areaId;
                }
            } else {
                // Coordinador y Colaborador: tickets de su área
                $targetArea = ($userAreaId > 0) ? $userAreaId : $areaId;
                if ($targetArea > 0) {
                    $sql .= " AND t.area_id = :area_id";
                    $params[':area_id'] = $targetArea;
                }
            }

            $sql .= " ORDER BY t.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Asignar ticket a un agente (Con Validación de Permisos RBAC)
     */
    public function assignAgent(int $ticketId, int $agentId, array $currentUser): array {
        try {
            $stmtTicket = $this->db->prepare("SELECT id, code, area_id, status_id FROM tickets WHERE id = :ticket_id");
            $stmtTicket->execute([':ticket_id' => $ticketId]);
            $ticket = $stmtTicket->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'El ticket no existe.', 'code' => 404];
            }

            $role = $currentUser['role_name'];
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            // Validar que el Admin o Coordinador del área puedan realizar asignaciones
            $isSelfPickup = ($agentId === (int)$currentUser['id']);
            
            if ($role !== 'admin') {
                if ($role !== 'manager' && !$isSelfPickup) {
                    return ['success' => false, 'message' => 'Solo los Coordinadores y Superadministradores pueden asignar tickets a otros.', 'code' => 403];
                }
                if ((int)$ticket['area_id'] !== $userAreaId) {
                    return ['success' => false, 'message' => 'No tiene permisos para interactuar con tickets de otra Área.', 'code' => 403];
                }
            }

            $stmtAgent = $this->db->prepare("SELECT id, name, area_id FROM users WHERE id = :agent_id AND is_active = 1");
            $stmtAgent->execute([':agent_id' => $agentId]);
            $agent = $stmtAgent->fetch();

            if (!$agent) {
                return ['success' => false, 'message' => 'Agente no válido o inactivo.', 'code' => 400];
            }

            if ((int)$agent['area_id'] !== (int)$ticket['area_id'] && $role !== 'admin') {
                return ['success' => false, 'message' => 'El agente seleccionado no pertenece a esta Área.', 'code' => 400];
            }

            $this->db->beginTransaction();

            $stmtUpdate = $this->db->prepare("
                UPDATE tickets 
                SET assigned_agent_id = :agent_id,
                    status_id = CASE WHEN status_id = 1 THEN 2 ELSE status_id END,
                    updated_at = NOW()
                WHERE id = :ticket_id
            ");
            $stmtUpdate->execute([':agent_id' => $agentId, ':ticket_id' => $ticketId]);

            $this->db->commit();

            return ['success' => true, 'message' => "Ticket {$ticket['code']} asignado exitosamente a {$agent['name']}."];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    /**
     * Obtener listado de tickets para la vista tabular estilo Freshdesk con RBAC
     */
    public function getAllTickets(array $currentUser, int $areaFilter = 0): array {
        try {
            $role = $currentUser['role_name'] ?? 'user';
            $userId = (int)($currentUser['id'] ?? 0);
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $sql = "
                SELECT t.id, t.code, t.title, t.description, t.priority, t.status_id, t.area_id, t.created_at,
                       ks.name as status_name, ks.slug as status_slug,
                       a.name as area_name,
                       u_req.name as requester_name, u_req.email as requester_email,
                       u_ag.name as agent_name, u_ag.id as assigned_agent_id
                FROM tickets t
                INNER JOIN kanban_status ks ON t.status_id = ks.id
                INNER JOIN areas a ON t.area_id = a.id
                INNER JOIN users u_req ON t.requester_id = u_req.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                WHERE 1=1
            ";

            $params = [];

            if ($role === 'admin') {
                if ($areaFilter > 0) {
                    $sql .= " AND t.area_id = :area_id";
                    $params[':area_id'] = $areaFilter;
                }
            } elseif ($role === 'manager') {
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

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Actualizar propiedades rápidas de un ticket (Estado, Prioridad)
     */
    public function updateProperties(int $ticketId, array $data, array $currentUser): array {
        try {
            $role = $currentUser['role_name'] ?? 'user';
            $userId = (int)($currentUser['id'] ?? 0);
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $stmt = $this->db->prepare("SELECT id, area_id, requester_id, assigned_agent_id FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            $isAdmin = ($role === 'admin');
            $isManager = ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId);
            $isAssigned = ((int)$ticket['assigned_agent_id'] === $userId);

            if (!$isAdmin && !$isManager && !$isAssigned) {
                return ['success' => false, 'message' => 'No tiene permisos para modificar propiedades de este ticket.', 'code' => 403];
            }

            $updates = [];
            $params = [':id' => $ticketId];

            if (isset($data['status_id']) && (int)$data['status_id'] > 0) {
                $updates[] = "status_id = :status_id";
                $params[':status_id'] = (int)$data['status_id'];
            }

            if (isset($data['priority']) && in_array($data['priority'], ['low', 'medium', 'high', 'urgent'], true)) {
                $updates[] = "priority = :priority";
                $params[':priority'] = $data['priority'];
            }

            if (empty($updates)) {
                return ['success' => true, 'message' => 'Sin cambios que aplicar.'];
            }

            $updates[] = "updated_at = NOW()";
            $sql = "UPDATE tickets SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmtUpdate = $this->db->prepare($sql);
            $stmtUpdate->execute($params);

            return ['success' => true, 'message' => 'Propiedades del ticket actualizadas exitosamente.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtener los tickets creados/solicitados por el usuario autenticado (Mis Solicitudes)
     */
    public function getMyRequestedTickets(int $userId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.code, t.title, t.description, t.priority, t.status_id, t.area_id, t.created_at,
                       ks.name as status_name, ks.slug as status_slug,
                       a.name as area_name,
                       u_ag.name as agent_name,
                       surv.rating as csat_rating, surv.rating as survey_rating, surv.id as survey_id, surv.feedback as csat_feedback
                FROM tickets t
                INNER JOIN kanban_status ks ON t.status_id = ks.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                LEFT JOIN ticket_satisfaction_surveys surv ON t.id = surv.ticket_id
                WHERE t.requester_id = :user_id
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([':user_id' => $userId]);
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

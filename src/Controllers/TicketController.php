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
            $companyName = htmlspecialchars(trim($data['company_name'] ?? 'Kaizen Holdings'), ENT_QUOTES, 'UTF-8');
            if (empty($companyName)) {
                $companyName = 'Kaizen Holdings';
            }

            // Área: IT o SSGG
            $areaCode = strtoupper(trim($data['area_code'] ?? ''));
            $areaId = (int)($data['area_id'] ?? 0);
            if ($areaCode === 'SSGG' || $areaId === 3) {
                $areaCode = 'SSGG';
                $areaId = 3;
                $prefix = 'SG';
            } else {
                $areaCode = 'IT';
                $areaId = 1;
                $prefix = 'IT';
            }

            // Responsable y búsqueda de agente
            $responsable = htmlspecialchars(trim($data['responsable'] ?? ''), ENT_QUOTES, 'UTF-8');
            $assignedAgentId = null;
            if (!empty($responsable) && $responsable !== 'Sin Asignar') {
                $stmtUser = $this->db->prepare("SELECT id FROM users WHERE name = :name LIMIT 1");
                $stmtUser->execute([':name' => $responsable]);
                $userFound = $stmtUser->fetch();
                if ($userFound) {
                    $assignedAgentId = (int)$userFound['id'];
                }
            } else {
                $responsable = null;
            }

            // Observaciones / Descripción
            $observations = htmlspecialchars(trim($data['observations'] ?? ($data['description'] ?? '')), ENT_QUOTES, 'UTF-8');
            $description = $observations ?: $title;

            // Estatus inicial
            $spStatus = trim($data['sp_status'] ?? 'Pendiente');
            $validStatuses = ['Pendiente', 'En proceso', 'Stand By', 'Procesado'];
            if (!in_array($spStatus, $validStatuses, true)) {
                $spStatus = 'Pendiente';
            }
            $statusId = match ($spStatus) {
                'En proceso' => 2,
                'Stand By'   => 3,
                'Procesado'  => 4,
                default      => 1, // 'Pendiente'
            };

            // Fecha propuesta (due_date)
            $dueDate = null;
            if (!empty($data['due_date'])) {
                $ts = strtotime($data['due_date']);
                if ($ts) {
                    $dueDate = date('Y-m-d H:i:s', $ts);
                }
            }

            // Prioridad
            $priority = $data['priority'] ?? 'medium';
            $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($priority, $allowedPriorities, true)) {
                $priority = 'medium';
            }

            if (empty($title)) {
                return ['success' => false, 'message' => 'El asunto / solicitud es obligatorio.'];
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

            $year = date('Y');
            $syncSharePoint = !empty($data['sync_sharepoint']) && ($data['sync_sharepoint'] === '1' || $data['sync_sharepoint'] === 'true' || $data['sync_sharepoint'] === true);
            $isDryRun = !empty($data['dry_run']) && ($data['dry_run'] === '1' || $data['dry_run'] === 'true' || $data['dry_run'] === true);

            $spId = null;
            $spNotice = '';

            // OPCIÓN DE CREACIÓN DIRECTA EN SHAREPOINT (PRODUCCIÓN / LOCAL)
            if ($syncSharePoint) {
                try {
                    $spService = new \Services\SharePointService();
                    $spResult = $spService->createSharePointItem([
                        'title'        => $title,
                        'company_name' => $companyName,
                        'area_code'    => $areaCode,
                        'responsable'  => $responsable ?: '',
                        'observations' => $observations,
                        'sp_status'    => $spStatus,
                        'dry_run'      => $isDryRun
                    ]);

                    if ($spResult['success'] && !empty($spResult['item_id'])) {
                        $spId = (int)$spResult['item_id'];
                        $spNotice = " y sincronizado en SharePoint (#{$spId})";
                    } else {
                        $spNotice = " (Aviso SharePoint: " . ($spResult['message'] ?? 'No se pudo crear en SharePoint') . ")";
                    }
                } catch (\Throwable $t) {
                    $spNotice = " (Aviso SharePoint: " . $t->getMessage() . ")";
                }
            }

            // Formato de código: si se creó en SharePoint se usa el estándar de SharePoint IT-SP-00123 / SG-SP-00123
            if ($spId !== null && $spId > 0) {
                $code = sprintf("%s-SP-%05d", $prefix, $spId);
            } else {
                $stmtCount = $this->db->prepare("SELECT COUNT(*) as total FROM tickets WHERE area_id = :area_id");
                $stmtCount->execute([':area_id' => $areaId]);
                $count = (int)$stmtCount->fetch()['total'] + 1;
                $code = sprintf("%s-%s-%03d", $prefix, $year, $count);
            }

            $stmt = $this->db->prepare("
                INSERT INTO tickets (
                    sharepoint_id, code, title, company_name, area_code, 
                    responsable, observations, sp_status, description, priority, 
                    status_id, area_id, requester_id, assigned_agent_id, 
                    due_date, attachment_path
                ) VALUES (
                    :sharepoint_id, :code, :title, :company_name, :area_code, 
                    :responsable, :observations, :sp_status, :description, :priority, 
                    :status_id, :area_id, :requester_id, :assigned_agent_id, 
                    :due_date, :attachment_path
                )
            ");

            $stmt->execute([
                ':sharepoint_id'      => $spId,
                ':code'               => $code,
                ':title'              => $title,
                ':company_name'       => $companyName,
                ':area_code'          => $areaCode,
                ':responsable'         => $responsable,
                ':observations'       => $observations,
                ':sp_status'          => $spStatus,
                ':description'        => $description,
                ':priority'           => $priority,
                ':status_id'          => $statusId,
                ':area_id'            => $areaId,
                ':requester_id'       => $currentUser['id'],
                ':assigned_agent_id'  => $assignedAgentId,
                ':due_date'           => $dueDate,
                ':attachment_path'    => $attachmentPath
            ]);

            $ticketId = $this->db->lastInsertId();

            return [
                'success'       => true,
                'message'       => "Ticket {$code} generado exitosamente{$spNotice}" . ($attachmentPath ? " con archivo adjunto." : "."),
                'ticket_id'     => $ticketId,
                'sharepoint_id' => $spId,
                'code'          => $code
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
                LEFT JOIN kanban_status ks ON t.status_id = ks.id
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN users u_req ON t.requester_id = u_req.id
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
            $ticket['survey'] = null;
            try {
                $stmtSurvey = $this->db->prepare("SELECT rating, feedback, created_at FROM ticket_satisfaction_surveys WHERE ticket_id = :id");
                $stmtSurvey->execute([':id' => $ticketId]);
                $ticket['survey'] = $stmtSurvey->fetch() ?: null;
            } catch (\Throwable $e) {
                $ticket['survey'] = null;
            }

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

            $sql .= " ORDER BY t.created_at DESC";

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
                SELECT t.id, t.sharepoint_id, t.code, t.title, t.company_name, t.area_code, t.responsable, t.observations, t.sp_status,
                       t.description, t.priority, t.status_id, t.area_id, t.created_at, t.due_date, t.resolved_at,
                       ks.name as status_name, ks.slug as status_slug,
                       a.name as area_name,
                       u_req.name as requester_name, u_req.email as requester_email,
                       u_ag.name as agent_name, u_ag.id as assigned_agent_id
                FROM tickets t
                INNER JOIN kanban_status ks ON t.status_id = ks.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users u_req ON t.requester_id = u_req.id
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

    /**
     * Actualizar información de un registro en SharePoint y sincronizar con la base de datos local
     */
    public function updateSharePointTicket(int $ticketId, array $data, array $currentUser): array {
        try {
            $role = $currentUser['role_name'] ?? 'user';
            $userId = (int)($currentUser['id'] ?? 0);
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $stmt = $this->db->prepare("SELECT * FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            $spId = (int)($ticket['sharepoint_id'] ?? 0);
            if ($spId <= 0) {
                return ['success' => false, 'message' => 'Este ticket no está vinculado a ningún registro de SharePoint.'];
            }

            $isAdmin = ($role === 'admin');
            $isManager = ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId);
            $isAssigned = ((int)$ticket['assigned_agent_id'] === $userId);
            $isAreaStaff = in_array($role, ['colaborador', 'agent', 'manager', 'admin'], true) && ((int)$ticket['area_id'] === $userAreaId || $isAdmin);

            if (!$isAdmin && !$isManager && !$isAssigned && !$isAreaStaff) {
                return ['success' => false, 'message' => 'No tiene permisos para modificar este ticket.', 'code' => 403];
            }

            // Preparar payload para SharePoint
            $spPayload = [];
            $dbUpdates = [];
            $dbParams = [':id' => $ticketId];

            // 1. Título / Asunto
            if (isset($data['title']) && trim($data['title']) !== '') {
                $newTitle = trim($data['title']);
                $spPayload['title'] = $newTitle;
                $dbUpdates[] = "title = :title";
                $dbParams[':title'] = $newTitle;
            }

            // 2. Empresa
            if (isset($data['company_name']) && trim($data['company_name']) !== '') {
                $newComp = trim($data['company_name']);
                $spPayload['company_name'] = $newComp;
                $dbUpdates[] = "company_name = :company_name";
                $dbParams[':company_name'] = $newComp;
            }

            // 3. Área (IT / SSGG)
            if (isset($data['area_code']) && trim($data['area_code']) !== '') {
                $newAreaCode = strtoupper(trim($data['area_code']));
                if (!in_array($newAreaCode, ['IT', 'SSGG'], true)) {
                    return ['success' => false, 'message' => 'El área debe ser IT o SSGG.'];
                }
                $spPayload['area_code'] = $newAreaCode;
                $dbUpdates[] = "area_code = :area_code";
                $dbParams[':area_code'] = $newAreaCode;

                $targetAreaId = ($newAreaCode === 'SSGG') ? 3 : 1;
                $dbUpdates[] = "area_id = :area_id";
                $dbParams[':area_id'] = $targetAreaId;
            }

            // 4. Responsable
            if (isset($data['responsable'])) {
                $newResp = trim($data['responsable']);
                $spPayload['responsable'] = $newResp;
                $dbUpdates[] = "responsable = :responsable";
                $dbParams[':responsable'] = $newResp;

                if (!empty($newResp) && $newResp !== 'Sin Asignar') {
                    $stmtUser = $this->db->prepare("SELECT id FROM users WHERE name = :name LIMIT 1");
                    $stmtUser->execute([':name' => $newResp]);
                    $agentUser = $stmtUser->fetch();
                    if ($agentUser) {
                        $dbUpdates[] = "assigned_agent_id = :agent_id";
                        $dbParams[':agent_id'] = (int)$agentUser['id'];
                    }
                }
            }

            // 5. Observaciones
            if (array_key_exists('observations', $data)) {
                $newObs = trim((string)$data['observations']);
                $spPayload['observations'] = $newObs;
                $dbUpdates[] = "observations = :observations";
                $dbParams[':observations'] = $newObs;
            }

            // 5.1 Prioridad
            if (isset($data['priority']) && trim($data['priority']) !== '') {
                $newPrio = strtolower(trim($data['priority']));
                if (in_array($newPrio, ['low', 'medium', 'high', 'urgent'], true)) {
                    $dbUpdates[] = "priority = :priority";
                    $dbParams[':priority'] = $newPrio;
                }
            }

            // 6. Estatus
            if (isset($data['sp_status']) && trim($data['sp_status']) !== '') {
                $newStatus = trim($data['sp_status']);
                $validStatuses = ['Pendiente', 'En proceso', 'Stand By', 'Procesado'];
                $matchedStatus = null;
                foreach ($validStatuses as $vs) {
                    if (strcasecmp($vs, $newStatus) === 0) {
                        $matchedStatus = $vs;
                        break;
                    }
                }
                if (!$matchedStatus) {
                    return ['success' => false, 'message' => 'Estatus no válido. Opciones permitidas: Pendiente, En proceso, Stand By, Procesado.'];
                }
                $spPayload['sp_status'] = $matchedStatus;
                $dbUpdates[] = "sp_status = :sp_status";
                $dbParams[':sp_status'] = $matchedStatus;

                $statusIdMap = [
                    'Procesado'  => 4,
                    'En proceso' => 2,
                    'Stand By'   => 3,
                    'Pendiente'  => 1
                ];
                $newStatusId = $statusIdMap[$matchedStatus] ?? 1;
                $dbUpdates[] = "status_id = :status_id";
                $dbParams[':status_id'] = $newStatusId;

                if ($matchedStatus === 'Procesado') {
                    $dbUpdates[] = "resolved_at = IFNULL(resolved_at, NOW())";
                } else {
                    $dbUpdates[] = "resolved_at = NULL";
                }
            }

            if (empty($spPayload)) {
                return ['success' => false, 'message' => 'No se indicaron cambios para actualizar.'];
            }

            // ACTUALIZACIÓN EN SHAREPOINT MEDIANTE GRAPH API
            $spService = new \Services\SharePointService();
            $spResult = $spService->updateSharePointItem($spId, $spPayload);

            if (!$spResult['success']) {
                return [
                    'success' => false,
                    'message' => 'No se pudo actualizar en SharePoint: ' . ($spResult['message'] ?? 'Error desconocido')
                ];
            }

            // ACTUALIZACIÓN EN BASE DE DATOS LOCAL
            $dbUpdates[] = "updated_at = NOW()";
            $sql = "UPDATE tickets SET " . implode(', ', $dbUpdates) . " WHERE id = :id";
            $stmtUpdate = $this->db->prepare($sql);
            $stmtUpdate->execute($dbParams);

            // Registro en auditoría
            try {
                $stmtAudit = $this->db->prepare("
                    INSERT INTO ticket_audit_logs (ticket_id, user_id, action_type, old_value, new_value, created_at)
                    VALUES (:t_id, :u_id, 'sharepoint_update', :old, :new, NOW())
                ");
                $stmtAudit->execute([
                    ':t_id' => $ticketId,
                    ':u_id' => $userId,
                    ':old'  => json_encode([
                        'title' => $ticket['title'],
                        'company_name' => $ticket['company_name'],
                        'area_code' => $ticket['area_code'],
                        'responsable' => $ticket['responsable'],
                        'observations' => $ticket['observations'],
                        'sp_status' => $ticket['sp_status']
                    ], JSON_UNESCAPED_UNICODE),
                    ':new'  => json_encode($spPayload, JSON_UNESCAPED_UNICODE)
                ]);
            } catch (\Throwable $e) {}

            // Obtener ticket actualizado
            $stmtRefetch = $this->db->prepare("
                SELECT t.*, 
                       ks.name as status_name, ks.slug as status_slug,
                       a.name as area_name,
                       u_ag.name as agent_name
                FROM tickets t
                INNER JOIN kanban_status ks ON t.status_id = ks.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                WHERE t.id = :id
            ");
            $stmtRefetch->execute([':id' => $ticketId]);
            $updatedTicket = $stmtRefetch->fetch();

            return [
                'success' => true,
                'message' => 'Registro actualizado exitosamente en SharePoint y sincronizado en el sistema.',
                'ticket'  => $updatedTicket
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Exportar / Vincular un ticket local existente a SharePoint
     */
    public function exportTicketToSharePoint(int $ticketId, array $currentUser, bool $dryRun = false): array {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, a.name as area_name, u.name as requester_name, u_ag.name as agent_name
                FROM tickets t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN users u ON t.requester_id = u.id
                LEFT JOIN users u_ag ON t.assigned_agent_id = u_ag.id
                WHERE t.id = :id
            ");
            $stmt->execute([':id' => $ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket no encontrado.', 'code' => 404];
            }

            if (!empty($ticket['sharepoint_id'])) {
                return [
                    'success' => false, 
                    'message' => "Este ticket ya está vinculado a SharePoint con ID #{$ticket['sharepoint_id']}."
                ];
            }

            $areaName = $ticket['area_name'] ?? '';
            $isSSGG = str_contains(strtoupper($areaName), 'SSGG') || str_contains(strtoupper($areaName), 'MANTENIMIENTO') || ((int)$ticket['area_id'] === 3);
            $areaCode = $isSSGG ? 'SSGG' : 'IT';
            $prefix = $isSSGG ? 'SG' : 'IT';

            $spService = new \Services\SharePointService();
            $spResult = $spService->createSharePointItem([
                'title'        => $ticket['title'],
                'company_name' => $ticket['company_name'] ?: ($ticket['requester_name'] ?: 'Kaizen Holdings'),
                'area_code'    => $areaCode,
                'responsable'  => $ticket['responsable'] ?: ($ticket['agent_name'] ?: ''),
                'observations' => $ticket['observations'] ?: $ticket['description'],
                'sp_status'    => $ticket['sp_status'] ?: 'Pendiente',
                'dry_run'      => $dryRun
            ]);

            if (!$spResult['success'] || empty($spResult['item_id'])) {
                return [
                    'success' => false,
                    'message' => 'Error al crear en SharePoint: ' . ($spResult['message'] ?? 'Respuesta inválida.')
                ];
            }

            $newSpId = (int)$spResult['item_id'];
            $newCode = sprintf("%s-SP-%05d", $prefix, $newSpId);

            $updateStmt = $this->db->prepare("
                UPDATE tickets 
                SET sharepoint_id = :sp_id, 
                    code = :code, 
                    area_code = :area_code, 
                    company_name = COALESCE(company_name, :company_name),
                    sp_status = COALESCE(sp_status, 'Pendiente')
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':sp_id'        => $newSpId,
                ':code'         => $newCode,
                ':area_code'    => $areaCode,
                ':company_name' => $ticket['company_name'] ?: 'Kaizen Holdings',
                ':id'           => $ticketId
            ]);

            return [
                'success'       => true,
                'sharepoint_id' => $newSpId,
                'code'          => $newCode,
                'message'       => "Ticket vinculado exitosamente a SharePoint con ID #{$newSpId}."
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al vincular con SharePoint: ' . $e->getMessage()];
        }
    }

    /**
     * Eliminar un ticket (con verificación de permisos, limpieza de adjuntos y cascada DB/SharePoint)
     */
    public function deleteTicket(int $ticketId, array $currentUser, bool $deleteInSharePoint = false, bool $dryRun = false): array {
        try {
            $stmt = $this->db->prepare("
                SELECT id, code, title, area_id, requester_id, status_id, sharepoint_id, attachment_path 
                FROM tickets 
                WHERE id = :id 
                LIMIT 1
            ");
            $stmt->execute([':id' => $ticketId]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                return ['success' => false, 'message' => 'El ticket no existe o ya fue eliminado.', 'code' => 404];
            }

            $role = $currentUser['role_name'] ?? '';
            $userId = (int)($currentUser['id'] ?? 0);
            $userAreaId = (int)($currentUser['area_id'] ?? 0);

            $isAdmin = ($role === 'admin');
            $isAreaManager = ($role === 'manager' && (int)$ticket['area_id'] === $userAreaId);
            $isOwner = ((int)$ticket['requester_id'] === $userId);

            // Permisos: Superadmin, Manager del área correspondiente, o el solicitante si está en estado inicial (1)
            $canDelete = $isAdmin || $isAreaManager || ($isOwner && (int)$ticket['status_id'] === 1);

            if (!$canDelete) {
                return [
                    'success' => false,
                    'message' => 'Acceso denegado: No tiene permisos suficientes para eliminar este ticket.',
                    'code' => 403
                ];
            }

            $spNotice = '';
            // Si el ticket está enlazado a SharePoint y se solicitó eliminarlo allí
            if ($deleteInSharePoint && !empty($ticket['sharepoint_id'])) {
                $spService = new \Services\SharePointService();
                $spRes = $spService->deleteSharePointItem((int)$ticket['sharepoint_id'], $dryRun);
                if (!empty($spRes['message'])) {
                    $spNotice = ' (' . $spRes['message'] . ')';
                }
            }

            // Eliminar archivo adjunto físico si existe en el disco
            if (!empty($ticket['attachment_path'])) {
                $filePath = __DIR__ . '/../../public/' . ltrim($ticket['attachment_path'], '/\\');
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            // Eliminar ticket de la BD local (MySQL aplica ON DELETE CASCADE a comentarios, auditoría y encuestas)
            $stmtDel = $this->db->prepare("DELETE FROM tickets WHERE id = :id");
            $stmtDel->execute([':id' => $ticketId]);

            return [
                'success'   => true,
                'ticket_id' => $ticketId,
                'code'      => $ticket['code'],
                'message'   => "El ticket {$ticket['code']} ha sido eliminado correctamente{$spNotice}."
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al eliminar el ticket: ' . $e->getMessage()];
        }
    }
}



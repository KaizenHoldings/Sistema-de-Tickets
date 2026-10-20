<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/TicketController.php';
require_once __DIR__ . '/../src/Controllers/KanbanController.php';
require_once __DIR__ . '/../src/Controllers/CommentController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/AreaController.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';

use Middleware\AuthMiddleware;
use Controllers\AuthController;
use Controllers\TicketController;
use Controllers\KanbanController;
use Controllers\CommentController;
use Controllers\UserController;
use Controllers\AreaController;
use Controllers\ReportController;

AuthMiddleware::startSession();

$action = $_GET['action'] ?? '';
$inputJSON = json_decode(file_get_contents('php://input'), true);
$input = !empty($_POST) ? $_POST : ($inputJSON ?? []);

try {
    // 1. AUTENTICACIÓN LIBRE
    if ($action === 'login') {
        $auth = new AuthController();
        $response = $auth->login($input['email'] ?? '', $input['password'] ?? '');
        echo json_encode($response);
        exit;
    }

    if ($action === 'logout') {
        $auth = new AuthController();
        $auth->logout();
        echo json_encode(['success' => true, 'message' => 'Sesión cerrada.']);
        exit;
    }

    if ($action === 'export_tickets_csv') {
        $currentUser = AuthMiddleware::requireAuth();
        $controller = new ReportController();
        $controller->exportTicketsCSV($currentUser);
        exit;
    }

    // VERIFICACIÓN MANDATORIA DE AUTENTICACIÓN
    $currentUser = AuthMiddleware::requireAuth();

    // VALIDACIÓN CSRF PARA ACCIONES DE ESCRITURA / MODIFICACIÓN (POST)
    $isWriteAction = in_array($action, ['create_ticket', 'update_kanban_status', 'update_ticket_properties', 'assign_agent', 'pickup_ticket', 'add_comment', 'create_area', 'create_user', 'toggle_user_role', 'submit_csat'], true);
    if ($isWriteAction && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        if (!AuthMiddleware::validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de seguridad (CSRF) inválido o expirado.']);
            exit;
        }
    }

    // 2. CREACIÓN Y CONSULTA DE TICKETS (CON PROTECCIÓN IDOR)
    if ($action === 'create_ticket') {
        $controller = new TicketController();
        $response = $controller->createTicket($_POST, $_FILES, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_ticket_detail') {
        $controller = new TicketController();
        $ticketId = (int)($_GET['ticket_id'] ?? 0);
        $response = $controller->getTicketDetail($ticketId, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_unassigned_tickets') {
        $controller = new TicketController();
        $areaId = isset($_GET['area_id']) ? (int)$_GET['area_id'] : (($currentUser['role_name'] === 'admin') ? 0 : (int)($currentUser['area_id'] ?? 0));
        $response = $controller->getUnassignedTickets($areaId, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_all_tickets') {
        $controller = new TicketController();
        $areaFilter = isset($_GET['area_id']) ? (int)$_GET['area_id'] : (($currentUser['role_name'] === 'admin') ? 0 : (int)($currentUser['area_id'] ?? 0));
        $response = $controller->getAllTickets($currentUser, $areaFilter);
        echo json_encode($response);
        exit;
    }

    if ($action === 'update_ticket_properties') {
        $controller = new TicketController();
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $response = $controller->updateProperties($ticketId, $input, $currentUser);
        echo json_encode($response);
        exit;
    }

    // 3. KANBAN Y MOVIMIENTOS CON VALIDACIÓN DE ROL
    if ($action === 'get_kanban_board') {
        $controller = new KanbanController();
        $areaId = isset($_GET['area_id']) ? (int)$_GET['area_id'] : (($currentUser['role_name'] === 'admin') ? 0 : (int)($currentUser['area_id'] ?? 0));
        $response = $controller->getBoardData($areaId, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'update_kanban_status') {
        $controller = new KanbanController();
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $newStatusId = (int)($input['status_id'] ?? 0);
        $comment = (string)($input['comment'] ?? '');
        $isInternal = (bool)($input['is_internal'] ?? false);
        $response = $controller->updateTicketStatus($ticketId, $newStatusId, $currentUser, $comment, $isInternal);
        echo json_encode($response);
        exit;
    }

    if ($action === 'submit_csat') {
        $controller = new TicketController();
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $rating = (int)($input['rating'] ?? 0);
        $feedback = (string)($input['feedback'] ?? '');
        $response = $controller->submitSatisfactionSurvey($ticketId, $rating, $feedback, $currentUser);
        echo json_encode($response);
        exit;
    }

    // 4. ASIGNACIÓN Y PICK UP
    if ($action === 'assign_agent') {
        $controller = new TicketController();
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $agentId = (int)($input['agent_id'] ?? 0);
        $response = $controller->assignAgent($ticketId, $agentId, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'pickup_ticket') {
        $controller = new TicketController();
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $response = $controller->assignAgent($ticketId, (int)$currentUser['id'], $currentUser);
        echo json_encode($response);
        exit;
    }

    // 5. REPORTES
    if ($action === 'get_dashboard_metrics') {
        $controller = new ReportController();
        $areaFilter = isset($_GET['area_id']) ? (int)$_GET['area_id'] : (($currentUser['role_name'] === 'admin') ? 0 : (int)($currentUser['area_id'] ?? 0));
        $response = $controller->getDashboardMetrics($currentUser, $areaFilter);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_my_requests') {
        $controller = new TicketController();
        $response = $controller->getMyRequestedTickets((int)$currentUser['id']);
        echo json_encode($response);
        exit;
    }

    // 6. COMENTARIOS Y NOTAS
    if ($action === 'get_comments') {
        $controller = new CommentController();
        $ticketId = (int)($_GET['ticket_id'] ?? 0);
        $response = $controller->getCommentsByTicket($ticketId, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'add_comment') {
        $controller = new CommentController();
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $comment = (string)($input['comment'] ?? '');
        $isInternal = (bool)($input['is_internal'] ?? false);
        $response = $controller->addComment($ticketId, $comment, $isInternal, $currentUser);
        echo json_encode($response);
        exit;
    }

    // 7. SUPERADMIN (RESTRINGIDO EXCLUSIVAMENTE A ROL 'admin')
    if ($action === 'get_agents_by_area') {
        $controller = new UserController();
        $areaId = (int)($_GET['area_id'] ?? 1);
        $response = $controller->getAgentsByArea($areaId);
        echo json_encode($response);
        exit;
    }

    if ($action === 'create_area') {
        AuthMiddleware::requireRoles(['admin']);
        $controller = new AreaController();
        $response = $controller->createArea($input);
        echo json_encode($response);
        exit;
    }

    if ($action === 'create_user') {
        AuthMiddleware::requireRoles(['admin']);
        $controller = new UserController();
        $response = $controller->createUser($input, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'toggle_user_role') {
        AuthMiddleware::requireRoles(['admin']);
        $controller = new UserController();
        $targetUserId = (int)($input['user_id'] ?? 0);
        $newRoleId = (int)($input['role_id'] ?? 3);
        $response = $controller->toggleUserRole($targetUserId, $newRoleId, $currentUser);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_all_users') {
        AuthMiddleware::requireRoles(['admin']);
        $controller = new UserController();
        $response = $controller->getAllUsers();
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_notifications') {
        $db = \Config\Database::getConnection();
        $userId = (int)$currentUser['id'];
        $userAreaId = (int)($currentUser['area_id'] ?? 0);
        $role = $currentUser['role_name'] ?? 'colaborador';
        $notifications = [];

        // 1. Unassigned tickets in department or all (for admin)
        $unassignedSql = "
            SELECT t.id, t.code, t.title, t.priority, t.created_at, a.name as area_name
            FROM tickets t
            INNER JOIN areas a ON t.area_id = a.id
            WHERE t.assigned_agent_id IS NULL AND t.status_id = 1
        ";
        if ($role !== 'admin' && $userAreaId > 0) {
            $unassignedSql .= " AND t.area_id = " . $userAreaId;
        }
        $unassignedSql .= " ORDER BY t.created_at DESC LIMIT 5";
        $stmtUnassigned = $db->query($unassignedSql);
        while ($row = $stmtUnassigned->fetch()) {
            $notifications[] = [
                'id' => 'unassigned_' . $row['id'],
                'ticket_id' => (int)$row['id'],
                'type' => 'unassigned',
                'title' => 'Nuevo ticket sin asignar: ' . $row['code'],
                'message' => $row['title'] . ' (' . $row['area_name'] . ')',
                'time' => $row['created_at'],
                'priority' => $row['priority'],
                'icon' => '📥'
            ];
        }

        // 2. Overdue / SLA Breached
        $overdueSql = "
            SELECT t.id, t.code, t.title, t.priority, t.created_at
            FROM tickets t
            WHERE t.is_sla_breached = 1 AND t.status_id != 4
        ";
        if ($role !== 'admin' && $userAreaId > 0) {
            $overdueSql .= " AND (t.area_id = {$userAreaId} OR t.assigned_agent_id = {$userId})";
        }
        $overdueSql .= " ORDER BY t.created_at DESC LIMIT 5";
        $stmtOverdue = $db->query($overdueSql);
        while ($row = $stmtOverdue->fetch()) {
            $notifications[] = [
                'id' => 'overdue_' . $row['id'],
                'ticket_id' => (int)$row['id'],
                'type' => 'overdue',
                'title' => 'Alerta SLA: ' . $row['code'],
                'message' => 'Ticket fuera de tiempo de resolución: ' . $row['title'],
                'time' => $row['created_at'],
                'priority' => $row['priority'],
                'icon' => '⚠️'
            ];
        }

        // 3. Tickets resolved where user is requester and hasn't rated CSAT
        $csatSql = "
            SELECT t.id, t.code, t.title, t.resolved_at
            FROM tickets t
            LEFT JOIN ticket_satisfaction_surveys s ON t.id = s.ticket_id
            WHERE t.status_id = 4 AND t.requester_id = :user_id AND s.id IS NULL
            ORDER BY t.resolved_at DESC LIMIT 3
        ";
        $stmtCsat = $db->prepare($csatSql);
        $stmtCsat->execute([':user_id' => $userId]);
        while ($row = $stmtCsat->fetch()) {
            $notifications[] = [
                'id' => 'csat_' . $row['id'],
                'ticket_id' => (int)$row['id'],
                'type' => 'csat',
                'title' => 'Encuesta CSAT: ' . $row['code'],
                'message' => 'Tu ticket fue resuelto. ¡Califica la atención recibida!',
                'time' => $row['resolved_at'] ?? date('Y-m-d H:i:s'),
                'priority' => 'medium',
                'icon' => '⭐'
            ];
        }

        // 4. Recent comments on tickets where user is requester or assigned
        $commentsSql = "
            SELECT c.id, c.ticket_id, c.comment, c.created_at, u.name as commenter_name, t.code as ticket_code
            FROM ticket_comments c
            INNER JOIN users u ON c.user_id = u.id
            INNER JOIN tickets t ON c.ticket_id = t.id
            WHERE c.user_id != :user_id 
              AND (t.requester_id = :user_id OR t.assigned_agent_id = :user_id)
              AND c.is_internal = 0
            ORDER BY c.created_at DESC LIMIT 5
        ";
        $stmtComments = $db->prepare($commentsSql);
        $stmtComments->execute([':user_id' => $userId]);
        while ($row = $stmtComments->fetch()) {
            $notifications[] = [
                'id' => 'comment_' . $row['id'],
                'ticket_id' => (int)$row['ticket_id'],
                'type' => 'comment',
                'title' => 'Nueva respuesta en ' . $row['ticket_code'],
                'message' => $row['commenter_name'] . ': ' . substr($row['comment'], 0, 60) . '...',
                'time' => $row['created_at'],
                'priority' => 'low',
                'icon' => '💬'
            ];
        }

        echo json_encode(['success' => true, 'data' => $notifications, 'unread_count' => count($notifications)]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}

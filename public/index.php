<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/Controllers/KanbanController.php';
require_once __DIR__ . '/../src/Controllers/AreaController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';
require_once __DIR__ . '/../src/Controllers/TicketController.php';
require_once __DIR__ . '/../src/Services/SharePointService.php';

use Middleware\AuthMiddleware;
use Controllers\KanbanController;
use Controllers\AreaController;
use Controllers\UserController;
use Controllers\ReportController;
use Controllers\TicketController;
use Services\SharePointService;

AuthMiddleware::startSession();
$currentUser = AuthMiddleware::requireAuth();
$csrfToken = AuthMiddleware::getCsrfToken();

// Auto-verificar superadmin (.env), esquema y sanear codificación
try {
    UserController::ensureSuperAdminExists();
    $spSvc = new SharePointService();
    $spSvc->ensureSchemaAndFixEncoding();
} catch (\Throwable $e) {}

$areas = [];
$users = [];
$reportData = [];
$allTickets = [];
$unassignedTickets = [];
$pendingTickets = [];
$pendingCountTotal = 0;
$pendingCountUnassigned = 0;
$pendingCountInProgress = 0;
$pendingCountStandBy = 0;
$pendingCountUrgent = 0;
$uniqueCompanies = [];
$uniqueStatuses = [];
$uniqueResponsables = [];
$uniqueRequesters = [];
$uniqueAgents = [];
$reportsAnalytics = null;

$userAreaId = (int)($currentUser['area_id'] ?? 1);
$role = $currentUser['role_name'] ?? 'colaborador';
$userId = (int)($currentUser['id'] ?? 0);

$tabAliases = [
    'dashboard'   => 'dashboard-tab',
    'tickets'     => 'tickets-tab',
    'my-requests' => 'my-requests-tab',
    'pending'     => 'pending-tab',
    'pendientes'  => 'pending-tab',
    'inbox'       => 'inbox-tab',
    'reports'     => 'reports-tab',
    'admin'       => 'admin-tab'
];
$rawTab = $_GET['tab'] ?? '';
$activeTab = $tabAliases[$rawTab] ?? ($rawTab ?: 'dashboard-tab');
$validTabs = ['dashboard-tab', 'tickets-tab', 'my-requests-tab', 'pending-tab', 'inbox-tab', 'reports-tab', 'admin-tab'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'dashboard-tab';
}
$initialPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$areaFilter = (isset($_GET['area_id']) && (int)$_GET['area_id'] > 0) ? (int)$_GET['area_id'] : (($role === 'admin') ? 0 : $userAreaId);

$roleDisplayNames = [
    'admin' => 'Superadministrador',
    'manager' => 'Coordinador',
    'colaborador' => 'Colaborador',
    'user' => 'Colaborador'
];
$displayRole = $roleDisplayNames[$role] ?? ucfirst($role);

$rawDemoTickets = [
    ['id' => 1, 'area_id' => 1, 'assigned_agent_id' => null, 'requester_id' => 8, 'code' => 'TI-2026-001', 'title' => 'Fallo de Red en Impresora Principal (Piso 3)', 'priority' => 'medium', 'status_id' => 1, 'requester_name' => 'Ana López', 'agent_name' => null],
    ['id' => 2, 'area_id' => 1, 'assigned_agent_id' => 5, 'requester_id' => 8, 'code' => 'TI-2026-002', 'title' => 'Lenta Conexión en Servidor de Correos', 'priority' => 'urgent', 'status_id' => 2, 'requester_name' => 'Ana López', 'agent_name' => 'Juan Pérez (Usuario TI)'],
    ['id' => 3, 'area_id' => 1, 'assigned_agent_id' => 2, 'requester_id' => 9, 'code' => 'TI-2026-003', 'title' => 'Configuración de Laptop para Nuevo Ingreso', 'priority' => 'low', 'status_id' => 2, 'requester_name' => 'Roberto Gómez', 'agent_name' => 'Carlos Mendoza (Coordinador TI)'],
    ['id' => 4, 'area_id' => 1, 'assigned_agent_id' => 6, 'requester_id' => 8, 'code' => 'TI-2026-004', 'title' => 'Reset de Contraseña Active Directory', 'priority' => 'low', 'status_id' => 4, 'requester_name' => 'Ana López', 'agent_name' => 'Laura Torres (Usuario TI)']
];

$filteredDemoTickets = array_filter($rawDemoTickets, function($t) use ($role, $userAreaId, $userId) {
    if ($role === 'admin') return true;
    if ($role === 'manager') return (int)$t['area_id'] === $userAreaId;
    return (int)$t['requester_id'] === $userId || (int)$t['assigned_agent_id'] === $userId;
});

$fallbackBoardData = [
    ['id' => 1, 'name' => 'Por Asignar', 'tickets' => array_values(array_filter($filteredDemoTickets, fn($t) => $t['status_id'] == 1))],
    ['id' => 2, 'name' => 'En Progreso', 'tickets' => array_values(array_filter($filteredDemoTickets, fn($t) => $t['status_id'] == 2))],
    ['id' => 3, 'name' => 'Pendiente Usuario', 'tickets' => array_values(array_filter($filteredDemoTickets, fn($t) => $t['status_id'] == 3))],
    ['id' => 4, 'name' => 'Resuelto', 'tickets' => array_values(array_filter($filteredDemoTickets, fn($t) => $t['status_id'] == 4))]
];

$myRequests = [];

try {
    $areaCtrl = new AreaController();
    $resAreas = $areaCtrl->getAllAreas();
    $areas = $resAreas['success'] ? $resAreas['data'] : [];

    $userCtrl = new UserController();
    $resUsers = $userCtrl->getAllUsers();
    $users = $resUsers['success'] ? $resUsers['data'] : [];

    $reportCtrl = new ReportController();
    $resReport = $reportCtrl->getDashboardMetrics($currentUser, $areaFilter);
    $reportData = $resReport['success'] ? $resReport : [];

    $resAnalytics = $reportCtrl->getReportsAnalytics($currentUser, ['area_id' => $areaFilter]);
    $reportsAnalytics = $resAnalytics['success'] ? $resAnalytics : null;

    $ticketCtrl = new TicketController();
    $resTickets = $ticketCtrl->getAllTickets($currentUser, $areaFilter);
    $allTickets = $resTickets['success'] ? $resTickets['data'] : [];

    $resMyReq = $ticketCtrl->getMyRequestedTickets($userId);
    $myRequests = $resMyReq['success'] ? $resMyReq['data'] : [];

    $resUnassigned = $ticketCtrl->getUnassignedTickets($areaFilter, $currentUser);
    $unassignedTickets = $resUnassigned['success'] ? $resUnassigned['data'] : [];

    // Tickets Pendientes de Atención (En curso, sin asignar o en espera, excluyendo Procesados/Resueltos)
    $pendingTickets = array_values(array_filter($allTickets, function($t) {
        $statusId = (int)($t['status_id'] ?? 1);
        $spStatus = mb_strtolower(trim($t['sp_status'] ?? ''));
        $statusSlug = mb_strtolower(trim($t['status_slug'] ?? ''));
        return $statusId !== 4 && $spStatus !== 'procesado' && $statusSlug !== 'resolved';
    }));

    $pendingCountTotal = count($pendingTickets);
    $pendingCountUnassigned = count(array_filter($pendingTickets, fn($t) => empty($t['assigned_agent_id']) || empty($t['responsable']) || $t['responsable'] === 'Sin Asignar'));
    $pendingCountInProgress = count(array_filter($pendingTickets, fn($t) => (int)($t['status_id'] ?? 1) === 2 || mb_strtolower(trim($t['sp_status'] ?? '')) === 'en proceso'));
    $pendingCountStandBy = count(array_filter($pendingTickets, fn($t) => (int)($t['status_id'] ?? 1) === 3 || mb_strtolower(trim($t['sp_status'] ?? '')) === 'stand by'));
    $pendingCountUrgent = count(array_filter($pendingTickets, fn($t) => in_array($t['priority'] ?? '', ['urgent', 'high'], true)));

    // Extraer opciones de filtros dinámicamente según lo que existe en los tickets de la tabla (Campos SharePoint)
    $uniqueCompanies = [];
    $uniqueStatuses = [
        'Pendiente'  => 'Pendiente',
        'En proceso' => 'En proceso',
        'Stand By'   => 'Stand By',
        'Procesado'  => 'Procesado'
    ];
    $uniqueAreas = [
        'IT'   => 'IT (Tecnología e Informática)',
        'SSGG' => 'SSGG (Servicios Generales)'
    ];
    $uniqueResponsables = [];

    // Mantener variables para compatibilidad
    $uniqueRequesters = [];
    $uniqueDepartments = [];
    $uniqueAgents = [];
    $uniquePriorities = [];

    foreach ($allTickets as $t) {
        // Empresa
        $comp = trim($t['company_name'] ?? $t['requester_name'] ?? '');
        if (!empty($comp) && !isset($uniqueCompanies[$comp])) {
            $uniqueCompanies[$comp] = $comp;
            $uniqueRequesters[mb_strtolower($comp)] = [
                'name' => $comp,
                'email' => $t['requester_email'] ?? '',
                'label' => $comp
            ];
        }

        // Área
        $ac = strtoupper(trim($t['area_code'] ?? ''));
        if (empty($ac)) {
            $ac = ((int)($t['area_id'] ?? 0) === 3) ? 'SSGG' : 'IT';
        }
        if (!isset($uniqueAreas[$ac])) {
            $uniqueAreas[$ac] = $ac;
        }
        $uniqueDepartments[(int)($t['area_id'] ?? 0)] = [
            'id' => (int)($t['area_id'] ?? 0),
            'name' => $ac
        ];

        // Responsable
        $resp = trim($t['responsable'] ?? $t['agent_name'] ?? '');
        if (!empty($resp) && $resp !== 'Sin Asignar' && !isset($uniqueResponsables[$resp])) {
            $uniqueResponsables[$resp] = $resp;
            $uniqueAgents[mb_strtolower($resp)] = [
                'id' => (int)($t['assigned_agent_id'] ?? 0),
                'name' => $resp
            ];
        }

        // Estatus
        $st = trim($t['sp_status'] ?? '');
        if (empty($st)) {
            $st = match ((int)($t['status_id'] ?? 0)) {
                2 => 'En proceso',
                3 => 'Stand By',
                4 => 'Procesado',
                default => 'Pendiente'
            };
        }
        if (!isset($uniqueStatuses[$st])) {
            $uniqueStatuses[$st] = $st;
        }
    }
    ksort($uniqueCompanies);
    ksort($uniqueResponsables);
    ksort($uniqueRequesters);
    ksort($uniqueAgents);
} catch (Throwable $e) {}

try {
    $kanbanCtrl = new KanbanController();
    $resBoard = $kanbanCtrl->getBoardData($areaFilter, $currentUser);
    $boardData = ($resBoard['success'] && !empty($resBoard['data'])) ? $resBoard['data'] : $fallbackBoardData;
} catch (Throwable $e) {
    $boardData = $fallbackBoardData;
}

$kpis = $reportData['kpis'] ?? [
    'total_tickets' => count($allTickets),
    'unresolved_tickets' => count(array_filter($allTickets, fn($t) => ($t['status_id'] ?? 1) != 4)),
    'overdue_tickets' => 0,
    'due_today_tickets' => 1,
    'open_tickets' => count(array_filter($allTickets, fn($t) => ($t['status_id'] ?? 1) == 2)),
    'waiting_customer_tickets' => count(array_filter($allTickets, fn($t) => ($t['status_id'] ?? 1) == 3)),
    'unassigned_tickets' => count($unassignedTickets),
    'resolved_tickets' => count(array_filter($allTickets, fn($t) => ($t['status_id'] ?? 1) == 4)),
    'sla_compliance_rate' => '100%',
    'priorities' => ['urgent' => 1, 'high' => 1, 'medium' => 1, 'low' => 1]
];
$kpis['unassigned_tickets'] = count($unassignedTickets);

$recentTickets = $reportData['recent_tickets'] ?? array_slice($allTickets, 0, 6);
$agentWorkload = $reportData['agent_workload'] ?? [];
$userInitials = strtoupper(substr($currentUser['name'] ?? 'U', 0, 2));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Servicio de asistencia técnica : Kaizen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script>
        window.CSRF_TOKEN = "<?= htmlspecialchars($csrfToken) ?>";
        window.CURRENT_USER_AREA_ID = <?= (int)$userAreaId ?>;
        window.CURRENT_USER_ROLE = "<?= htmlspecialchars($role) ?>";
        window.CURRENT_USER_ID = <?= (int)$userId ?>;
        window.CURRENT_USER_NAME = "<?= htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
    </script>
</head>
<body>

<div class="fd-layout">

    <!-- BARRA LATERAL DE NAVEGACIÓN -->
    <?php include __DIR__ . '/../views/layout/sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL DERECHO -->
    <div class="fd-main">
        <!-- BARRA SUPERIOR -->
        <?php include __DIR__ . '/../views/layout/topbar.php'; ?>

        <!-- CONTENEDOR PRINCIPAL DE PESTAÑAS -->
        <main class="fd-content-container">
            <?php include __DIR__ . '/../views/tabs/dashboard.php'; ?>
            <?php include __DIR__ . '/../views/tabs/tickets.php'; ?>
            <?php include __DIR__ . '/../views/tabs/my-requests.php'; ?>
            <?php include __DIR__ . '/../views/tabs/pending.php'; ?>
            <?php include __DIR__ . '/../views/tabs/inbox.php'; ?>
            <?php include __DIR__ . '/../views/tabs/reports.php'; ?>
            <?php if ($currentUser['role_name'] === 'admin'): ?>
                <?php include __DIR__ . '/../views/tabs/admin.php'; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- ====================================================================
     MODALES DEL SISTEMA (MODULARES)
     ==================================================================== -->
<?php include __DIR__ . '/../views/modals/create-ticket.php'; ?>
<?php include __DIR__ . '/../views/modals/ticket-detail.php'; ?>
<?php include __DIR__ . '/../views/modals/assign-agent.php'; ?>
<?php include __DIR__ . '/../views/modals/csat.php'; ?>
<?php if ($currentUser['role_name'] === 'admin'): ?>
    <?php include __DIR__ . '/../views/modals/user-modals.php'; ?>
<?php endif; ?>

<!-- ====================================================================
     SCRIPTS GLOBALES, CHART.JS Y CONTROLADORES
     ==================================================================== -->
<?php include __DIR__ . '/../views/layout/footer_scripts.php'; ?>

</body>
</html>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/Controllers/KanbanController.php';
require_once __DIR__ . '/../src/Controllers/AreaController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/ReportController.php';
require_once __DIR__ . '/../src/Controllers/TicketController.php';

use Middleware\AuthMiddleware;
use Controllers\KanbanController;
use Controllers\AreaController;
use Controllers\UserController;
use Controllers\ReportController;
use Controllers\TicketController;

AuthMiddleware::startSession();
$currentUser = AuthMiddleware::requireAuth();
$csrfToken = AuthMiddleware::getCsrfToken();

$areas = [];
$users = [];
$reportData = [];
$allTickets = [];
$unassignedTickets = [];

$userAreaId = (int)($currentUser['area_id'] ?? 1);
$role = $currentUser['role_name'] ?? 'colaborador';
$userId = (int)($currentUser['id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'dashboard-tab';
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

    $ticketCtrl = new TicketController();
    $resTickets = $ticketCtrl->getAllTickets($currentUser, $areaFilter);
    $allTickets = $resTickets['success'] ? $resTickets['data'] : [];

    $resMyReq = $ticketCtrl->getMyRequestedTickets($userId);
    $myRequests = $resMyReq['success'] ? $resMyReq['data'] : [];

    $resUnassigned = $ticketCtrl->getUnassignedTickets($areaFilter, $currentUser);
    $unassignedTickets = $resUnassigned['success'] ? $resUnassigned['data'] : [];
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
    <link rel="stylesheet" href="css/style.css">
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

    <!-- FRESHDESK LEFT VERTICAL SIDEBAR -->
    <aside class="fd-sidebar">
        <div class="fd-sidebar-top">
            <div class="fd-brand-logo" title="Kaizen ServiceDesk" onclick="switchNavTab('dashboard-tab')">
                K
            </div>

            <nav class="fd-nav-menu">
                <!-- 1. DASHBOARD / PANEL DE CONTROL -->
                <button class="fd-nav-item nav-tab <?= ($activeTab === 'dashboard-tab') ? 'active' : '' ?>" data-target="dashboard-tab" data-tooltip="Panel de Control">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="7" height="9" rx="1"/>
                        <rect x="14" y="3" width="7" height="5" rx="1"/>
                        <rect x="14" y="12" width="7" height="9" rx="1"/>
                        <rect x="3" y="16" width="7" height="5" rx="1"/>
                    </svg>
                    <span>Inicio</span>
                </button>

                <!-- 2. TICKETS (DUAL LISTA / KANBAN) -->
                <button class="fd-nav-item nav-tab <?= ($activeTab === 'tickets-tab') ? 'active' : '' ?>" data-target="tickets-tab" data-tooltip="Tickets de Soporte">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <span>Tickets</span>
                </button>

                <!-- 2.5. MIS SOLICITUDES CREADAS (HECHOS POR MÍ PARA OTRAS ÁREAS) -->
                <button class="fd-nav-item nav-tab <?= ($activeTab === 'my-requests-tab') ? 'active' : '' ?>" data-target="my-requests-tab" data-tooltip="Mis Solicitudes Creadas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M22 2L11 13"/>
                        <path d="M22 2l-7 20-4-9-9-4 20-7z"/>
                    </svg>
                    <span>Mis Solicitudes</span>
                </button>

                <!-- 3. BANDEJA DE ASIGNACIÓN / INBOX (ACCESO PARA LOS 3 ROLES) -->
                <button class="fd-nav-item nav-tab <?= ($activeTab === 'inbox-tab') ? 'active' : '' ?>" data-target="inbox-tab" data-tooltip="Bandeja de Entrada / Inbox" style="position: relative;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                        <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                    </svg>
                    <span>Inbox</span>
                    <span id="inbox-badge-count" style="background: #EF4444; color: white; border-radius: 50%; padding: 0.1rem 0.4rem; font-size: 10px; font-weight: bold; position: absolute; top: 0.25rem; right: 0.25rem; box-shadow: 0 0 5px rgba(239,68,68,0.5); <?= (($kpis['unassigned_tickets'] ?? 0) > 0) ? '' : 'display:none;' ?>">
                        <?= (int)($kpis['unassigned_tickets'] ?? 0) ?>
                    </span>
                </button>

                <!-- 4. REPORTES & KPIS -->
                <button class="fd-nav-item nav-tab <?= ($activeTab === 'reports-tab') ? 'active' : '' ?>" data-target="reports-tab" data-tooltip="Informes y SLAs">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                    <span>Informes</span>
                </button>

                <!-- 5. SUPERADMIN (SOLO ADMIN) -->
                <?php if ($currentUser['role_name'] === 'admin'): ?>
                    <button class="fd-nav-item nav-tab <?= ($activeTab === 'admin-tab') ? 'active' : '' ?>" data-target="admin-tab" data-tooltip="Administración">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        <span>Ajustes</span>
                    </button>
                <?php endif; ?>
            </nav>
        </div>

        <div class="fd-sidebar-bottom">
            <div class="fd-avatar-circle" title="<?= htmlspecialchars($currentUser['name']) ?> (<?= htmlspecialchars($role) ?>)">
                <?= htmlspecialchars($userInitials) ?>
                <span class="fd-avatar-status" title="En línea"></span>
            </div>
            <button class="fd-sidebar-btn" title="Cerrar Sesión" onclick="logout()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </button>
        </div>
    </aside>

    <!-- MAIN RIGHT WRAPPER -->
    <div class="fd-main">

        <!-- TOP BAR FRESHDESK -->
        <header class="fd-topbar">
            <div class="fd-topbar-left">
                <span class="fd-portal-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                    </svg>
                    Servicio de asistencia técnica : Kaizen
                </span>
                <?php
                $activeAreaBadge = 'General';
                if ($role === 'admin') {
                    if ($areaFilter > 0) {
                        foreach ($areas as $a) {
                            if ((int)$a['id'] === $areaFilter) {
                                $activeAreaBadge = 'Área: ' . $a['name'];
                                break;
                            }
                        }
                    } else {
                        $activeAreaBadge = 'Área: Todas las Áreas';
                    }
                } else {
                    $activeAreaBadge = $currentUser['area_name'] ?? 'General';
                }
                ?>
                <span class="fd-portal-badge"><?= htmlspecialchars($activeAreaBadge) ?></span>
            </div>

            <div class="fd-topbar-center">
                <!-- Buscador movido a las áreas específicas -->
            </div>

            <div class="fd-topbar-right">
                <?php if ($currentUser['role_name'] === 'admin'): ?>
                    <div style="width: 220px;">
                        <select class="fd-prop-select" id="kanban-area-select" onchange="filterAreaChange(this.value)" style="padding: 0.35rem 0.6rem; font-size:12px;">
                            <option value="0" <?= ($areaFilter == 0) ? 'selected' : '' ?>>🌐 Ver Todas las Áreas</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= $area['id'] ?>" <?= ($areaFilter == $area['id']) ? 'selected' : '' ?>>
                                    🏢 <?= htmlspecialchars($area['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- CENTRO DE NOTIFICACIONES -->
                <div class="fd-notification-wrapper">
                    <button type="button" id="topbar-notif-btn" class="fd-notification-bell" onclick="toggleNotificationsDropdown()" title="Notificaciones del Servicio">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span id="topbar-notif-badge" class="fd-notification-badge" style="display:none;">0</span>
                    </button>

                    <div id="topbar-notif-dropdown" class="fd-notifications-dropdown">
                        <div class="fd-notif-header">
                            <h4>🔔 Notificaciones</h4>
                            <span id="topbar-notif-count" class="badge">0 nuevas</span>
                        </div>
                        <div id="topbar-notif-list" class="fd-notif-body">
                            <div class="fd-notif-empty">Cargando notificaciones...</div>
                        </div>
                    </div>
                </div>

                <div class="fd-user-summary">
                    <span><strong><?= htmlspecialchars($currentUser['name']) ?></strong></span>
                    <span class="fd-user-role-badge"><?= htmlspecialchars($displayRole) ?></span>
                </div>
            </div>
        </header>

        <!-- CONTENEDOR DE CONTENIDO -->
        <main class="fd-content-container">

            <!-- ====================================================================
                 PESTAÑA 1: DASHBOARD EXACTO DE FRESHDESK (/a/dashboard/default)
                 ==================================================================== -->
            <section id="dashboard-tab" class="tab-content <?= ($activeTab === 'dashboard-tab') ? 'active' : '' ?>">
                <div class="fd-view-header">
                    <div class="fd-view-title">
                        <h1>Panel de Control</h1>
                        <p>Resumen operacional del servicio de asistencia técnica : Kaizen</p>
                    </div>
                    <div class="fd-header-actions">
                        <a href="api.php?action=export_tickets_csv" class="btn-secondary" style="text-decoration:none; display:inline-flex;">
                            📥 Exportar Reporte a Excel (CSV)
                        </a>
                    </div>
                </div>

                <!-- 1. FILA DE 6 TARJETAS MÉTRICAS DE FRESHDESK -->
                <div class="fd-kpi-grid">
                    <div class="fd-kpi-card unresolved" onclick="filterAndShowTickets('unresolved')">
                        <div class="fd-kpi-label">No resueltos</div>
                        <div class="fd-kpi-number"><?= $kpis['unresolved_tickets'] ?? 0 ?></div>
                        <div class="fd-kpi-subtext">Requieren atención</div>
                    </div>

                    <div class="fd-kpi-card overdue" onclick="filterAndShowTickets('overdue')">
                        <div class="fd-kpi-label" style="color:#DC2626;">Vencidos</div>
                        <div class="fd-kpi-number"><?= $kpis['overdue_tickets'] ?? 0 ?></div>
                        <div class="fd-kpi-subtext">Fuera de plazo SLA</div>
                    </div>

                    <div class="fd-kpi-card due-today" onclick="filterAndShowTickets('due_today')">
                        <div class="fd-kpi-label" style="color:#EA580C;">Vencen hoy</div>
                        <div class="fd-kpi-number"><?= $kpis['due_today_tickets'] ?? 0 ?></div>
                        <div class="fd-kpi-subtext">Atención prioritaria</div>
                    </div>

                    <div class="fd-kpi-card open" onclick="filterAndShowTickets('open')">
                        <div class="fd-kpi-label" style="color:#2563EB;">Abiertos</div>
                        <div class="fd-kpi-number"><?= $kpis['open_tickets'] ?? 0 ?></div>
                        <div class="fd-kpi-subtext">En gestión por agente</div>
                    </div>

                    <div class="fd-kpi-card waiting" onclick="filterAndShowTickets('waiting')">
                        <div class="fd-kpi-label">En espera</div>
                        <div class="fd-kpi-number"><?= $kpis['waiting_customer_tickets'] ?? 0 ?></div>
                        <div class="fd-kpi-subtext">Pendiente respuesta usuario</div>
                    </div>

                    <div class="fd-kpi-card unassigned" onclick="filterAndShowTickets('unassigned')">
                        <div class="fd-kpi-label" style="color:#7C3AED;">Sin asignar</div>
                        <div class="fd-kpi-number"><?= $kpis['unassigned_tickets'] ?? 0 ?></div>
                        <div class="fd-kpi-subtext">Por delegar a técnico</div>
                    </div>
                </div>

                <!-- 2. FILA DE WIDGETS INTERACTIVOS FRESHDESK -->
                <div class="fd-widgets-row">
                    <!-- WIDGET: DISTRIBUCIÓN POR PRIORIDAD -->
                    <div class="fd-widget-card">
                        <div class="fd-widget-header">
                            <h3>Tickets no resueltos por prioridad</h3>
                            <span>Volumen actual</span>
                        </div>
                        <?php
                            $prios = $kpis['priorities'] ?? ['urgent'=>1, 'high'=>1, 'medium'=>1, 'low'=>1];
                            $totUnres = max(1, ($kpis['unresolved_tickets'] ?? 1));
                        ?>
                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#DC2626;">🔴 Urgente</span>
                                <span><?= $prios['urgent'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['urgent'] ?? 0) / $totUnres) * 100)) ?>%; background:#EF4444;"></div>
                            </div>
                        </div>

                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#EA580C;">🟠 Alta</span>
                                <span><?= $prios['high'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['high'] ?? 0) / $totUnres) * 100)) ?>%; background:#F97316;"></div>
                            </div>
                        </div>

                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#D97706;">🟡 Media</span>
                                <span><?= $prios['medium'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['medium'] ?? 0) / $totUnres) * 100)) ?>%; background:#F59E0B;"></div>
                            </div>
                        </div>

                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#059669;">🟢 Baja</span>
                                <span><?= $prios['low'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['low'] ?? 0) / $totUnres) * 100)) ?>%; background:#10B981;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- WIDGET: CUMPLIMIENTO DE SLA -->
                    <div class="fd-widget-card">
                        <div class="fd-widget-header">
                            <h3>Rendimiento del Servicio (SLA)</h3>
                            <span>Tiempo de resolución</span>
                        </div>
                        <div class="fd-sla-box">
                            <div class="fd-sla-circle">
                                <span class="pct"><?= htmlspecialchars($kpis['sla_compliance_rate'] ?? '100%') ?></span>
                                <span class="lbl">Dentro de SLA</span>
                            </div>
                            <div class="fd-sla-info">
                                <div class="fd-sla-stat">✅ Tickets en plazo: <strong><?= max(0, ($kpis['total_tickets'] ?? 0) - ($kpis['overdue_tickets'] ?? 0)) ?></strong></div>
                                <div class="fd-sla-stat">⚠️ Excedidos: <strong style="color:#DC2626;"><?= $kpis['overdue_tickets'] ?? 0 ?></strong></div>
                                <div class="fd-sla-stat">🎯 Meta corporativa: <strong>95.0%</strong></div>
                            </div>
                        </div>
                    </div>

                    <!-- WIDGET: CARGA POR COLABORADOR -->
                    <div class="fd-widget-card">
                        <div class="fd-widget-header">
                            <h3>Carga de trabajo por agente</h3>
                            <span>Tickets asignados</span>
                        </div>
                        <?php if (empty($agentWorkload)): ?>
                            <p style="color:var(--fd-text-dim); font-size:12px; text-align:center; padding:1.5rem 0;">No hay tickets asignados a agentes actualmente.</p>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                <?php foreach (array_slice($agentWorkload, 0, 4) as $ag): ?>
                                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px;">
                                        <span>👤 <?= htmlspecialchars($ag['agent_name']) ?></span>
                                        <span class="ticket-count"><?= (int)$ag['assigned_tickets'] ?> activos</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. TABLA DE TICKETS RECIENTES QUE REQUIEREN ATENCIÓN -->
                <div class="fd-table-card">
                    <div class="fd-table-header">
                        <h3>Tickets recientes que requieren atención</h3>
                        <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px;" onclick="switchNavTab('tickets-tab')">
                            Ver todos los tickets &rarr;
                        </button>
                    </div>

                    <table class="fd-table">
                        <thead>
                            <tr>
                                <th>Código y Asunto</th>
                                <th>Solicitante</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Agente Asignado</th>
                                <th>Creado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTickets)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:var(--fd-text-dim); padding:2rem;">
                                        No hay tickets registrados en esta vista.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTickets as $t): ?>
                                    <tr data-ticket-id="<?= (int)$t['id'] ?>">
                                        <td>
                                            <span class="fd-ticket-code"><?= htmlspecialchars($t['code']) ?></span>
                                            <a class="fd-ticket-link" onclick="openTicketModal(<?= $t['id'] ?>)">
                                                <?= htmlspecialchars($t['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fd-user-cell">
                                                <div class="fd-avatar-sm"><?= strtoupper(substr($t['requester_name'] ?? 'U', 0, 2)) ?></div>
                                                <span><?= htmlspecialchars($t['requester_name'] ?? 'Usuario') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fd-prio-badge fd-prio-<?= htmlspecialchars($t['priority']) ?>">
                                                <?= htmlspecialchars($t['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fd-status-badge fd-status-<?= htmlspecialchars($t['status_slug'] ?? 'unassigned') ?>">
                                                <?= htmlspecialchars($t['status_name'] ?? 'Por Asignar') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $t['agent_name'] ? htmlspecialchars($t['agent_name']) : '<em style="color:#94a3b8">Sin Asignar</em>' ?>
                                        </td>
                                        <td style="color:var(--fd-text-dim); font-size:12px;">
                                            <?= htmlspecialchars(substr($t['created_at'] ?? '', 0, 16)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>


            <!-- ====================================================================
                 PESTAÑA 2: MÓDULO DE TICKETS (DUAL: LISTA FRESHDESK + KANBAN)
                 ==================================================================== -->
            <section id="tickets-tab" class="tab-content <?= ($activeTab === 'tickets-tab') ? 'active' : '' ?>">
                <div class="fd-view-header">
                    <div class="fd-view-title">
                        <h1 id="tickets-view-heading" style="display:inline-flex; align-items:center; gap:0.6rem;">
                            <span>Todos los tickets</span>
                            <span id="reset-tickets-filter" style="display:none; font-size:12px; font-weight:normal; color:#2563EB; cursor:pointer; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:12px; padding:0.15rem 0.6rem;" onclick="filterAndShowTickets('all')">✖ Ver todos</span>
                        </h1>
                        <p>Vista general de solicitudes asignadas y en progreso</p>
                    </div>

                    <div class="fd-header-actions">
                        <!-- BUSCADOR MUDADO AQUÍ -->
                        <div class="fd-search-bar" style="margin-right: 1rem; width: 250px;">
                            <svg class="fd-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" id="tickets-search-input" class="fd-search-input" placeholder="Buscar tickets..." oninput="filterTicketsGlobal(this.value)">
                        </div>
                        <!-- TOGGLE VISTA LISTA VS VISTA KANBAN -->
                        <div class="fd-view-toggle">
                            <button id="btn-toggle-list" class="fd-view-toggle-btn active" onclick="switchTicketsView('list')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                                    <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                                </svg>
                                Lista
                            </button>
                            <button id="btn-toggle-kanban" class="fd-view-toggle-btn" onclick="switchTicketsView('kanban')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                                </svg>
                                Tablero Kanban
                            </button>
                        </div>

                        <button class="btn-secondary" style="font-size:12px; padding:0.45rem 0.8rem; display:inline-flex; align-items:center; gap:0.35rem;" onclick="switchNavTab('my-requests-tab')">
                            📤 Mis Solicitudes (<?= count($myRequests) ?>)
                        </button>

                        <button class="btn-primary" onclick="openCreateTicketModal()">
                            + Crear Solicitud
                        </button>
                    </div>
                </div>

                <!-- SUB-VISTA 1: LISTA TABULAR ESTILO FRESHDESK -->
                <div id="tickets-subview-list" class="fd-table-card">
                    <table class="fd-table" id="all-tickets-table">
                        <thead>
                            <tr>
                                <th>Código y Asunto</th>
                                <th>Solicitante</th>
                                <th>Departamento</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Agente Asignado</th>
                                <th>Fecha</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="all-tickets-tbody">
                            <?php if (empty($allTickets)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">
                                        No hay tickets registrados en esta vista.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allTickets as $t): ?>
                                    <tr class="ticket-row" data-ticket-id="<?= (int)$t['id'] ?>" data-priority="<?= htmlspecialchars($t['priority']) ?>" data-status-id="<?= (int)$t['status_id'] ?>" data-agent-id="<?= (int)($t['assigned_agent_id'] ?? 0) ?>">
                                        <td>
                                            <span class="fd-ticket-code"><?= htmlspecialchars($t['code']) ?></span>
                                            <a class="fd-ticket-link" onclick="openTicketModal(<?= $t['id'] ?>)">
                                                <?= htmlspecialchars($t['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fd-user-cell">
                                                <div class="fd-avatar-sm"><?= strtoupper(substr($t['requester_name'] ?? 'U', 0, 2)) ?></div>
                                                <div>
                                                    <div style="font-weight:600;"><?= htmlspecialchars($t['requester_name']) ?></div>
                                                    <div style="font-size:11px; color:var(--fd-text-dim);"><?= htmlspecialchars($t['requester_email'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($t['area_name'] ?? '') ?></td>
                                        <td>
                                            <span class="fd-prio-badge fd-prio-<?= htmlspecialchars($t['priority']) ?>">
                                                <?= htmlspecialchars($t['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fd-status-badge fd-status-<?= htmlspecialchars($t['status_slug'] ?? 'unassigned') ?>">
                                                <?= htmlspecialchars($t['status_name'] ?? 'Por Asignar') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="agent-name-display">
                                                <?= $t['agent_name'] ? htmlspecialchars($t['agent_name']) : '<em style="color:#94a3b8">Sin Asignar</em>' ?>
                                            </span>
                                        </td>
                                        <td style="color:var(--fd-text-dim); font-size:12px;">
                                            <?= htmlspecialchars(substr($t['created_at'] ?? '', 0, 16)) ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:0.4rem; align-items:center;">
                                                <button class="btn-secondary" style="padding:0.3rem 0.6rem; font-size:11px;" onclick="openTicketModal(<?= $t['id'] ?>)">
                                                    Ver Detalle
                                                </button>
                                                <?php if (in_array($role, ['admin', 'manager'])): ?>
                                                    <?php if (empty($t['assigned_agent_id'])): ?>
                                                        <button class="btn-primary" style="padding:0.3rem 0.6rem; font-size:11px; background:#10B981;" onclick="openAssignAgentDialog(<?= $t['id'] ?>, <?= $t['area_id'] ?>)">
                                                            👤 Asignar Agente
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-secondary" style="padding:0.3rem 0.6rem; font-size:11px;" onclick="openAssignAgentDialog(<?= $t['id'] ?>, <?= $t['area_id'] ?>)">
                                                            🔄 Reasignar Agente
                                                        </button>
                                                    <?php endif; ?>
                                                <?php elseif ($role === 'colaborador' && empty($t['assigned_agent_id']) && (int)$t['area_id'] === $userAreaId): ?>
                                                    <button class="btn-primary" style="padding:0.3rem 0.6rem; font-size:11px; background:#10B981;" onclick="pickupTicket(<?= $t['id'] ?>)">
                                                        ⚡ Tomar Ticket
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- SUB-VISTA 2: TABLERO KANBAN CON COLUMNAS FRESHDESK -->
                <div id="tickets-subview-kanban" style="display:none;">
                    <div class="kanban-board">
                        <?php foreach ($boardData as $column): ?>
                            <div class="kanban-column">
                                <div class="column-header">
                                    <h3>
                                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#0284C7;"></span>
                                        <?= htmlspecialchars($column['name']) ?>
                                    </h3>
                                    <span class="ticket-count"><?= count($column['tickets']) ?></span>
                                </div>

                                <div class="tickets-container" data-status-id="<?= $column['id'] ?>">
                                    <?php if (empty($column['tickets'])): ?>
                                        <div style="text-align:center; padding:2rem 1rem; color:var(--fd-text-dim); font-size:12px; border:1px dashed #CBD5E1; border-radius:6px;">
                                            Sin tickets en esta columna
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($column['tickets'] as $ticket): ?>
                                            <div class="ticket-card prio-<?= htmlspecialchars($ticket['priority']) ?>" draggable="true" data-ticket-id="<?= $ticket['id'] ?>">
                                                <div class="ticket-code"><?= htmlspecialchars($ticket['code']) ?></div>
                                                <div class="ticket-title"><?= htmlspecialchars($ticket['title']) ?></div>

                                                <div style="display: flex; gap: 0.4rem; margin-bottom: 0.4rem;">
                                                    <span class="fd-prio-badge fd-prio-<?= htmlspecialchars($ticket['priority']) ?>">
                                                        <?= htmlspecialchars($ticket['priority']) ?>
                                                    </span>
                                                </div>

                                                <div class="ticket-footer">
                                                    <span>
                                                        👤 <?= $ticket['agent_name'] ? htmlspecialchars($ticket['agent_name']) : '<em style="color:#94a3b8">Sin Asignar</em>' ?>
                                                    </span>
                                                    <span style="font-size:11px; color:#2563EB;">Ver &rarr;</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>


            <!-- ====================================================================
                 PESTAÑA 2.5: MIS SOLICITUDES CREADAS (REQUERIMIENTOS HECHOS POR MÍ)
                 ==================================================================== -->
            <section id="my-requests-tab" class="tab-content <?= ($activeTab === 'my-requests-tab') ? 'active' : '' ?>">
                <div class="fd-view-header">
                    <div class="fd-view-title">
                        <h1>Mis Solicitudes y Requerimientos</h1>
                        <p>Consulte el estatus, agente asignado y resolución de todos los tickets solicitados por usted para cualquier departamento</p>
                    </div>

                    <div class="fd-header-actions">
                        <!-- BUSCADOR PARA MIS SOLICITUDES -->
                        <div class="fd-search-bar" style="margin-right: 1rem; width: 250px;">
                            <svg class="fd-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" id="myreq-search-input" class="fd-search-input" placeholder="Buscar mis tickets..." oninput="filterMyRequests(this.value)">
                        </div>

                        <button class="btn-primary" onclick="openCreateTicketModal()">
                            + Nueva Solicitud
                        </button>
                    </div>
                </div>

                <!-- 4 TARJETAS KPI DE MIS SOLICITUDES -->
                <?php
                $myTotal = count($myRequests);
                $myOpen = count(array_filter($myRequests, fn($r) => in_array($r['status_id'], [1, 2])));
                $myWaiting = count(array_filter($myRequests, fn($r) => $r['status_id'] == 3));
                $myResolved = count(array_filter($myRequests, fn($r) => $r['status_id'] == 4));
                ?>
                <div class="fd-kpi-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.5rem;">
                    <div class="fd-kpi-card unresolved">
                        <div class="fd-kpi-label">Total Solicitudes</div>
                        <div class="fd-kpi-number"><?= $myTotal ?></div>
                        <div class="fd-kpi-subtext">Generadas por mí</div>
                    </div>
                    <div class="fd-kpi-card open">
                        <div class="fd-kpi-label" style="color:#0284C7;">En Gestión</div>
                        <div class="fd-kpi-number" style="color:#0284C7;"><?= $myOpen ?></div>
                        <div class="fd-kpi-subtext">Por asignar o en progreso</div>
                    </div>
                    <div class="fd-kpi-card due-today">
                        <div class="fd-kpi-label" style="color:#EA580C;">Esperando Respuesta</div>
                        <div class="fd-kpi-number" style="color:#EA580C;"><?= $myWaiting ?></div>
                        <div class="fd-kpi-subtext">Pendiente de respuesta</div>
                    </div>
                    <div class="fd-kpi-card resolved">
                        <div class="fd-kpi-label" style="color:#10B981;">Resueltos / Cerrados</div>
                        <div class="fd-kpi-number" style="color:#10B981;"><?= $myResolved ?></div>
                        <div class="fd-kpi-subtext">Atención finalizada</div>
                    </div>
                </div>

                <!-- TABLA DETALLADA DE MIS SOLICITUDES -->
                <div class="fd-table-card">
                    <div class="fd-table-header">
                        <h3>Historial de Solicitudes Creadas</h3>
                        <span style="font-size:12px; color:var(--fd-text-dim);">Actualizado en tiempo real</span>
                    </div>

                    <table class="fd-table" id="my-requests-table">
                        <thead>
                            <tr>
                                <th>Código y Asunto</th>
                                <th>Departamento Destino</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Agente Responsable</th>
                                <th>Fecha Solicitud</th>
                                <th>Calificación de Servicio</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myRequests)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3rem 1rem; color:var(--fd-text-dim);">
                                        <div style="font-size:2.5rem; margin-bottom:0.5rem;">📝</div>
                                        <div style="font-size:15px; font-weight:600; color:var(--fd-text);">No has creado solicitudes de soporte aún</div>
                                        <p style="font-size:13px; margin:0.35rem 0 1rem 0;">Cuando necesites asistencia de TI, RRHH u otra área, presiona el botón de abajo para generar tu ticket.</p>
                                        <button class="btn-primary" onclick="openCreateTicketModal()">+ Crear Primera Solicitud</button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($myRequests as $mr): ?>
                                    <tr class="ticket-row" data-ticket-id="<?= (int)$mr['id'] ?>">
                                        <td>
                                            <span class="fd-ticket-code"><?= htmlspecialchars($mr['code']) ?></span>
                                            <a class="fd-ticket-link" onclick="openTicketModal(<?= $mr['id'] ?>)">
                                                <?= htmlspecialchars($mr['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="fd-portal-badge" style="font-size:11px;">🏢 <?= htmlspecialchars($mr['area_name'] ?? 'General') ?></span>
                                        </td>
                                        <td>
                                            <span class="fd-prio-badge fd-prio-<?= htmlspecialchars($mr['priority']) ?>">
                                                <?= htmlspecialchars($mr['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fd-status-badge fd-status-<?= htmlspecialchars($mr['status_slug'] ?? 'unassigned') ?>">
                                                <?= htmlspecialchars($mr['status_name'] ?? 'Por Asignar') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="agent-name-display">
                                                <?= $mr['agent_name'] ? ('👤 ' . htmlspecialchars($mr['agent_name'])) : '<em style="color:#94a3b8">⏳ En espera de asignación</em>' ?>
                                            </span>
                                        </td>
                                        <td style="color:var(--fd-text-dim); font-size:12px;">
                                            <?= htmlspecialchars(substr($mr['created_at'] ?? '', 0, 16)) ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$mr['status_id'] === 4): ?>
                                                <?php if (!empty($mr['csat_rating'])): ?>
                                                    <span style="display:inline-flex; align-items:center; gap:0.3rem; background:#DCFCE7; color:#166534; padding:0.25rem 0.6rem; border-radius:12px; font-size:11px; font-weight:600;">
                                                        ⭐ <?= (int)$mr['csat_rating'] ?>/5 Calificado
                                                    </span>
                                                <?php else: ?>
                                                    <button class="btn-primary" style="background:#D97706; padding:0.25rem 0.55rem; font-size:11px;" onclick="openCsatModal(<?= $mr['id'] ?>, '<?= htmlspecialchars($mr['code']) ?>')">
                                                        ⭐ Calificar Atención
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--fd-text-dim); font-size:12px;">En atención...</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-secondary" style="padding:0.3rem 0.6rem; font-size:11px;" onclick="openTicketModal(<?= $mr['id'] ?>)">
                                                Ver Detalle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>


            <!-- ====================================================================
                 PESTAÑA 3: BANDEJA DE ASIGNACIÓN E INBOX (ACCESO PARA LOS 3 ROLES)
                 ==================================================================== -->
            <section id="inbox-tab" class="tab-content <?= ($activeTab === 'inbox-tab') ? 'active' : '' ?>">
                <div class="fd-view-header">
                    <div class="fd-view-title">
                        <h1>Bandeja de Entrada e Inbox de Asignación</h1>
                        <p><?= ($role === 'colaborador') ? 'Tickets entrantes y pendientes de su área de trabajo. Puede tomar un ticket para iniciar su atención inmediata.' : 'Evalúe los tickets entrantes del departamento y asígnelos a colaboradores del equipo' ?></p>
                    </div>
                    <div class="fd-header-actions">
                        <div class="fd-search-bar" style="width: 250px;">
                            <svg class="fd-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" id="inbox-search-input" class="fd-search-input" placeholder="Buscar en bandeja..." oninput="filterInboxTickets(this.value)">
                        </div>
                    </div>
                </div>

                <div class="fd-table-card">
                    <table class="fd-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Título del Ticket</th>
                                <th>Solicitante</th>
                                <th>Prioridad</th>
                                <th>Fecha de Envío</th>
                                <th>Acciones de Asignación</th>
                            </tr>
                        </thead>
                        <tbody id="unassigned-tickets-tbody">
                            <?php if (empty($unassignedTickets)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-muted);">✨ No hay tickets pendientes por asignar en esta área actualmente.</td></tr>
                            <?php else: ?>
                                <?php foreach ($unassignedTickets as $t): ?>
                                    <tr>
                                        <td>
                                            <strong style="cursor:pointer; color:var(--fd-action-btn);" onclick="openTicketModal(<?= (int)$t['id'] ?>)"><?= htmlspecialchars($t['code']) ?></strong>
                                        </td>
                                        <td>
                                            <a style="cursor:pointer; color:var(--fd-text); text-decoration:none; font-weight:500;" onclick="openTicketModal(<?= (int)$t['id'] ?>)">
                                                <?= htmlspecialchars($t['title']) ?>
                                            </a>
                                            <?= !empty($t['attachment_path']) ? '📎' : '' ?>
                                            <div style="font-size:11px; color:var(--fd-text-dim);">🏢 <?= htmlspecialchars($t['area_name'] ?? 'General') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($t['requester_name']) ?></td>
                                        <td><span class="fd-prio-badge fd-prio-<?= htmlspecialchars($t['priority']) ?>"><?= htmlspecialchars($t['priority']) ?></span></td>
                                        <td style="font-size:12px; color:var(--fd-text-dim);"><?= !empty($t['created_at']) ? substr($t['created_at'], 0, 16) : '' ?></td>
                                        <td>
                                            <?php if ($role === 'colaborador'): ?>
                                                <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981;" onclick="pickupTicket(<?= (int)$t['id'] ?>)">
                                                    ⚡ Tomar Ticket
                                                </button>
                                                <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="openTicketModal(<?= (int)$t['id'] ?>)">
                                                    Ver Detalle
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981;" onclick="openAssignAgentDialog(<?= (int)$t['id'] ?>, <?= (int)($t['area_id'] ?? 0) ?>)">
                                                    👤 Asignar Agente
                                                </button>
                                                <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="pickupTicket(<?= (int)$t['id'] ?>)">
                                                    ⚡ Tomar Ticket
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>


            <!-- ====================================================================
                 PESTAÑA 4: INFORMES Y METRICAS AVANZADAS (ROLE-BASED ANALYTICS)
                 ==================================================================== -->
            <!-- ====================================================================
                 PESTAÑA 4: INFORMES Y METRICAS AVANZADAS (SaaS Dashboard)
                 ==================================================================== -->
            <section id="reports-tab" class="tab-content <?= ($activeTab === 'reports-tab') ? 'active' : '' ?>">
                <div class="fd-view-header" style="align-items: flex-start;">
                    <div class="fd-view-title">
                        <h1 style="font-family: 'Inter', sans-serif; font-weight: 700;">Informes y Analíticas de Rendimiento</h1>
                        <p style="color: #64748b; font-size: 14px;">Gestión de Solicitudes y Tickets</p>
                    </div>
                    <div class="fd-header-actions" style="display:flex; gap:1rem;">
                        <select class="form-control" style="width:160px; font-size:13px; background:#fff; border-color:#e2e8f0; color:#334155;" onchange="simulateFilterUpdate()">
                            <option>Últimos 30 días</option>
                            <option>Este Mes</option>
                            <option>Último Trimestre</option>
                            <option>Este Año</option>
                        </select>
                        <select class="form-control" style="width:180px; font-size:13px; background:#fff; border-color:#e2e8f0; color:#334155;" onchange="simulateFilterUpdate()">
                            <option value="">Filtrar por agente...</option>
                            <option>Laura Torres</option>
                            <option>Carlos Mendoza</option>
                            <option>Sofía Ramírez</option>
                        </select>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="fd-kpi-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 2rem; gap: 1.5rem;">
                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Tickets Totales</span>
                            <span style="background:#f1f5f9; padding:0.3rem; border-radius:6px;">📊</span>
                        </div>
                        <div style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">2,458</div>
                        <div style="font-size:12px; color:#10b981; font-weight:600; margin-top:0.25rem;">+12% vs mes anterior</div>
                    </div>

                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Tiempo Medio de Resolución</span>
                            <span style="background:#f1f5f9; padding:0.3rem; border-radius:6px;">⏱️</span>
                        </div>
                        <div style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">4.2 horas</div>
                        <div style="font-size:12px; color:#10b981; font-weight:600; margin-top:0.25rem;">-0.5h vs mes anterior</div>
                    </div>

                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Satisfacción del Cliente (CSAT)</span>
                            <span style="background:#fef3c7; padding:0.3rem; border-radius:6px;">⭐</span>
                        </div>
                        <div style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">4.6 <span style="font-size:1rem; color:#94a3b8;">/ 5</span></div>
                        <div style="font-size:12px; color:#10b981; font-weight:600; margin-top:0.25rem;">Basado en 1,842 encuestas</div>
                    </div>

                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Resolución en Primer Contacto (FCR)</span>
                            <span style="background:#dcfce7; padding:0.3rem; border-radius:6px;">🎯</span>
                        </div>
                        <div style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">74%</div>
                        <div style="font-size:12px; color:#ef4444; font-weight:600; margin-top:0.25rem;">-2% vs mes anterior</div>
                    </div>
                </div>

                <!-- CHARTS GRID -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- 1. Line Chart -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Evolución Temporal de Tickets</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartLine', this.value)">
                                <option value="line" selected>Líneas</option>
                                <option value="bar">Barras</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartLine"></canvas>
                        </div>
                    </div>

                    <!-- 2. Bar Chart -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Tickets Resueltos por Agente</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartBar', this.value)">
                                <option value="line">Líneas</option>
                                <option value="bar" selected>Barras</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartBar"></canvas>
                        </div>
                    </div>

                    <!-- 3. Pie/Doughnut Chart -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Distribución por Prioridad</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartPie', this.value)">
                                <option value="line">Líneas</option>
                                <option value="bar">Barras</option>
                                <option value="doughnut" selected>Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%; display:flex; justify-content:center;">
                            <canvas id="chartPie"></canvas>
                        </div>
                    </div>

                    <!-- 4. Histogram -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Distribución del Tiempo de Resolución</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartHist', this.value)">
                                <option value="line">Líneas</option>
                                <option value="bar" selected>Barras</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartHist"></canvas>
                        </div>
                    </div>

                    <!-- 5. Scatter Plot (Fallback standard) -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Tiempo de Respuesta vs. Satisfacción</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartScatter', this.value)">
                                <option value="line">Líneas</option>
                                <option value="bar">Barras</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartScatter"></canvas>
                        </div>
                    </div>

                    <!-- 6. Boxplot (Fallback standard) -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Tiempos de Resolución por Prioridad</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartBoxplot', this.value)">
                                <option value="line">Líneas</option>
                                <option value="bar" selected>Barras</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartBoxplot"></canvas>
                        </div>
                    </div>
                </div>
            </section>


            <!-- ====================================================================
                 PESTAÑA 5: ADMINISTRACIÓN (SUPERADMIN GLOBAL)
                 ==================================================================== -->
            <?php if ($currentUser['role_name'] === 'admin'): ?>
            <section id="admin-tab" class="tab-content <?= ($activeTab === 'admin-tab') ? 'active' : '' ?>">
                <div class="fd-view-header">
                    <div class="fd-view-title">
                        <h1>Configuración y Administración Global</h1>
                        <p>Gestión de departamentos, colaboradores y asignación de roles</p>
                    </div>
                </div>

                <div class="fd-widgets-row" style="grid-template-columns: 1fr 1fr;">
                    <!-- CREAR NUEVA ÁREA -->
                    <div class="fd-widget-card">
                        <div class="fd-widget-header">
                            <h3>🏢 Crear Nuevo Departamento / Área</h3>
                        </div>
                        <form id="create-area-form" style="display:flex; flex-direction:column; gap:1rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Nombre del Departamento:</label>
                                <input type="text" name="name" class="form-control" placeholder="Ej: Seguridad y Auditoría" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Descripción del Departamento:</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Responsabilidades del área..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary" style="align-self:flex-start;">
                                Guardar Departamento
                            </button>
                        </form>
                    </div>

                    <!-- CREAR NUEVO USUARIO -->
                    <div class="fd-widget-card">
                        <div class="fd-widget-header">
                            <h3>👤 Dar de Alta Usuario / Colaborador</h3>
                        </div>
                        <form id="create-user-form" style="display:flex; flex-direction:column; gap:0.85rem;">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="font-size:11px; font-weight:600;">Nombre Completo:</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div>
                                    <label style="font-size:11px; font-weight:600;">Correo Electrónico:</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="font-size:11px; font-weight:600;">Departamento:</label>
                                    <select name="area_id" class="form-control">
                                        <option value="">-- Sin Área --</option>
                                        <?php foreach ($areas as $a): ?>
                                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:11px; font-weight:600;">Rol Asignado:</label>
                                    <select name="role_id" class="form-control">
                                        <option value="3">Usuario / Colaborador</option>
                                        <option value="2">Coordinador de Área</option>
                                        <option value="1">Superadministrador</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:600;">Contraseña Inicial:</label>
                                <input type="password" name="password" class="form-control" value="password123" required>
                            </div>
                            <button type="submit" class="btn-primary" style="align-self:flex-start;">
                                Registrar Usuario
                            </button>
                        </form>
                    </div>
                </div>

                <!-- LISTADO DE USUARIOS Y GESTIÓN DE ROLES -->
                <div class="fd-table-card" style="margin-top: 1.5rem;">
                    <div class="fd-table-header">
                        <h3>👥 Directorio de Colaboradores y Gestión de Roles</h3>
                        <span style="font-size:12px; color:var(--fd-text-dim);">Cambie el nivel de acceso en tiempo real</span>
                    </div>

                    <table class="fd-table">
                        <thead>
                            <tr>
                                <th>Nombre y Correo</th>
                                <th>Departamento</th>
                                <th>Rol Actual</th>
                                <th>Estado</th>
                                <th>Cambiar Rol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">No hay usuarios registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fd-user-cell">
                                                <div class="fd-avatar-sm"><?= strtoupper(substr($u['name'], 0, 2)) ?></div>
                                                <div>
                                                    <div style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></div>
                                                    <div style="font-size:11px; color:var(--fd-text-dim);"><?= htmlspecialchars($u['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['area_name'] ?? 'Sin Área Asignada') ?></td>
                                        <td>
                                            <span class="user-role-badge user-role-<?= htmlspecialchars($u['role_name']) ?>">
                                                <?= htmlspecialchars($roleDisplayNames[$u['role_name']] ?? ucfirst($u['role_name'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color:#10B981; font-weight:600; font-size:12px;">● Activo</span>
                                        </td>
                                        <td>
                                            <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                                <select class="fd-prop-select" style="font-size:12px; padding:0.3rem 0.5rem;" onchange="toggleUserRole(<?= $u['id'] ?>, this.value)">
                                                    <option value="3" <?= ($u['role_id'] == 3) ? 'selected' : '' ?>>Colaborador</option>
                                                    <option value="2" <?= ($u['role_id'] == 2) ? 'selected' : '' ?>>Coordinador</option>
                                                    <option value="1" <?= ($u['role_id'] == 1) ? 'selected' : '' ?>>Superadministrador</option>
                                                </select>
                                            <?php else: ?>
                                                <span style="font-size:11px; color:var(--fd-text-dim); font-style:italic;">Mi sesión actual</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

        </main>
    </div>
</div>


<!-- ====================================================================
     MODAL CREAR NUEVO TICKET (FRESHDESK + NUEVO)
     ==================================================================== -->
<div id="create-ticket-modal" class="modal-backdrop">
    <div class="modal-content" style="max-width: 680px;">
        <div class="modal-header">
            <h3>📝 Generar Nueva Solicitud de Servicio</h3>
            <button class="close-modal" onclick="closeCreateTicketModal()">&times;</button>
        </div>

        <form id="create-ticket-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="area_id">🏢 Departamento / Área de Destino:</label>
                <select name="area_id" id="area_id" class="form-control" required>
                    <option value="">-- Seleccionar Área de Destino --</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">📝 Asunto / Título del Ticket:</label>
                <input type="text" name="title" id="title" class="form-control" placeholder="Ej: Falla de red en piso 3 o Configuración de VPN" required>
            </div>

            <div class="form-group">
                <label for="priority">⚡ Prioridad Estimada:</label>
                <select name="priority" id="priority" class="form-control" required>
                    <option value="low">🟢 Baja (Requerimientos de rutina o consultas generales)</option>
                    <option value="medium" selected>🟡 Media (Inconveniente regular de impacto moderado)</option>
                    <option value="high">🟠 Alta (Falla importante que bloquea parcialmente)</option>
                    <option value="urgent">🔴 Urgente (Falla crítica o bloqueo total de labores)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">📄 Descripción Detallada del Requerimiento:</label>
                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Describa claramente los síntomas, la ubicación física o el problema presentado..." required></textarea>
            </div>

            <div class="form-group" style="background:#F8FAFC; border:1px dashed #CBD5E1; padding:0.85rem; border-radius:6px;">
                <label for="attachment">📷 Adjuntar Evidencia / Captura (Opcional):</label>
                <input type="file" name="attachment" id="attachment" class="form-control" accept="image/*,.pdf" style="padding:0.35rem; background:white;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn-secondary" onclick="closeCreateTicketModal()">Cancelar</button>
                <button type="submit" class="btn-primary">
                    🚀 Enviar Solicitud
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ====================================================================
     MODAL DETALLE DE TICKET FRESHDESK (/a/tickets/ID) A 2 COLUMNAS
     ==================================================================== -->
<div id="ticket-modal" class="modal-backdrop">
    <div class="fd-modal-ticket">
        <!-- CABECERA DE TICKET -->
        <div class="fd-modal-top">
            <div class="fd-modal-title-group">
                <h2 id="modal-ticket-title">Detalle del Ticket</h2>
                <span id="modal-ticket-status-pill" class="fd-status-badge">Estado</span>
                <span id="modal-ticket-prio-pill" class="fd-prio-badge">Prioridad</span>
            </div>
            <button class="close-modal" onclick="closeTicketModal()">&times;</button>
        </div>

        <!-- CUERPO DIVIDIDO A 2 COLUMNAS -->
        <div class="fd-modal-body-split">
            <!-- COLUMNA IZQUIERDA: HISTORIAL, CONVERSACIÓN Y NOTAS (70%) -->
            <div class="fd-detail-conversation">
                <input type="hidden" id="modal-ticket-id-input">

                <!-- BANNER CSAT (ENCUESTA DE SATISFACCIÓN) -->
                <div id="modal-csat-banner" style="display:none; margin-bottom:1rem;"></div>

                <!-- TARJETA DE REQUERIMIENTO ORIGINAL -->
                <div class="fd-initial-request-box">
                    <div class="fd-request-author">
                        <div class="fd-user-cell">
                            <div class="fd-avatar-sm" id="modal-author-avatar">U</div>
                            <div>
                                <strong id="modal-ticket-requester">-</strong>
                                <span style="color:var(--fd-text-dim); font-size:12px; margin-left:6px;" id="modal-ticket-date"></span>
                            </div>
                        </div>
                        <div style="font-size:12px; color:var(--fd-text-muted);">
                            Departamento: <strong id="modal-ticket-area" style="color:var(--fd-text-title);">-</strong>
                        </div>
                    </div>
                    <p id="modal-ticket-description" class="fd-request-desc"></p>

                    <!-- CONTENEDOR DE ARCHIVO ADJUNTO -->
                    <div id="modal-attachment-container" style="display:none; margin-top:1rem; padding-top:0.75rem; border-top:1px dashed #CBD5E1;">
                        <h4 style="font-size:12px; color:#2563EB; margin-bottom:0.5rem;">📎 Evidencia Adjunta:</h4>
                        <div id="modal-attachment-content"></div>
                    </div>
                </div>

                <!-- LÍNEA DE TIEMPO DE COMENTARIOS / NOTAS PRIVADAS -->
                <div>
                    <h3 style="font-size:13px; font-weight:700; color:var(--fd-text-title); margin-bottom:0.75rem;">
                        Historial de Conversación & Notas Internas
                    </h3>
                    <div id="comments-container" class="comments-timeline"></div>
                </div>

                <!-- CAJA DE RESPUESTA / NOTA PRIVADA -->
                <div class="fd-reply-box">
                    <div class="fd-reply-tabs">
                        <button type="button" id="tab-reply-public" class="fd-reply-tab-btn active" onclick="setCommentMode(false)">
                            💬 Responder al Solicitante
                        </button>
                        <?php if (in_array($currentUser['role_name'], ['admin', 'manager', 'colaborador', 'agent', 'user'])): ?>
                            <button type="button" id="tab-reply-private" class="fd-reply-tab-btn note-btn" onclick="setCommentMode(true)">
                                🔒 Agregar Nota Interna Privada
                            </button>
                        <?php endif; ?>
                    </div>

                    <textarea id="new-comment-text" class="form-control" rows="3" placeholder="Escriba su respuesta o detalle la acción realizada..."></textarea>
                    
                    <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:0.6rem;">
                        <button type="button" id="btn-submit-comment" class="btn-primary" onclick="handleCommentSubmit()">
                            Enviar Respuesta
                        </button>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA: SIDEBAR DE PROPIEDADES FRESHDESK (30%) -->
            <div class="fd-detail-sidebar">
                <div class="fd-sidebar-section-title">Propiedades del Ticket</div>

                <div class="fd-prop-group">
                    <label>Estado del Ticket:</label>
                    <select id="modal-prop-status-select" class="fd-prop-select" onchange="handleDetailStatusChange(this)">
                        <option value="1">Por Asignar</option>
                        <option value="2">En Progreso</option>
                        <option value="3">Pendiente Usuario</option>
                        <option value="4">Resuelto</option>
                    </select>
                </div>

                <div class="fd-prop-group">
                    <label>Prioridad:</label>
                    <select id="modal-prop-priority-select" class="fd-prop-select" onchange="updateTicketProperty('priority', this.value)">
                        <option value="low">🟢 Baja</option>
                        <option value="medium">🟡 Media</option>
                        <option value="high">🟠 Alta</option>
                        <option value="urgent">🔴 Urgente</option>
                    </select>
                </div>

                <div class="fd-prop-group">
                    <label>Agente Asignado:</label>
                    <div style="display:flex; flex-direction:column; gap:0.4rem;">
                        <span id="modal-ticket-agent" style="font-weight:600; font-size:13px; color:var(--fd-text-title);">-</span>
                        <?php if (in_array($currentUser['role_name'], ['admin', 'manager'])): ?>
                        <div id="modal-assign-btn-container">
                            <button type="button" class="btn-secondary" id="modal-assign-trigger-btn" style="width:100%; font-size:12px; padding:0.4rem;">
                                👤 Reasignar a Colaborador
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="fd-prop-group">
                    <label>SLA & Resolución:</label>
                    <div style="background:#F1F5F9; padding:0.6rem; border-radius:6px; font-size:12px;">
                        <span style="color:#059669; font-weight:700;">● Dentro del Tiempo de Servicio</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- MODAL DE ASIGNACIÓN A COLABORADOR DEL ÁREA -->
<div id="assign-modal" class="modal-backdrop" style="z-index: 1100;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>👤 Asignar Ticket a Colaborador</h3>
            <button class="close-assign-modal close-modal">&times;</button>
        </div>
        
        <form id="assign-agent-form">
            <input type="hidden" id="assign-ticket-id-input" name="ticket_id">
            
            <div class="form-group">
                <label for="assign-agent-select">Seleccione el Colaborador o Coordinador:</label>
                <select id="assign-agent-select" name="agent_id" class="form-control" required style="font-size:13px;">
                    <option value="">Cargando lista de usuarios del área...</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn-secondary close-assign-modal">Cancelar</button>
                <button type="submit" class="btn-primary">✅ Confirmar Asignación</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL DE CONFIRMACIÓN DE CAMBIO DE ESTADO (CON NOTA OBLIGATORIA)
     ==================================================================== -->
<div id="status-change-modal" class="modal-backdrop" style="z-index: 1150;">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <h3>🔄 Confirmar Cambio de Estado</h3>
            <button class="close-status-modal close-modal">&times;</button>
        </div>

        <form id="status-change-form">
            <input type="hidden" id="status-change-ticket-id">
            <input type="hidden" id="status-change-new-status-id">

            <div class="status-change-box">
                <div>
                    <div style="font-size:11px; color:#64748B; text-transform:uppercase; font-weight:700;">Ticket</div>
                    <strong id="status-change-code" style="color:#0284C7; font-size:14px;">-</strong>
                </div>
                <div class="status-transition-display">
                    <span id="status-change-from-badge" class="fd-status-badge">-</span>
                    <span>➔</span>
                    <span id="status-change-to-badge" class="fd-status-badge">-</span>
                </div>
            </div>

            <div class="form-group">
                <label>Tipo de Registro:</label>
                <div class="status-change-type-toggle">
                    <button type="button" id="status-toggle-public" class="status-type-btn active" onclick="setStatusNoteType(false)">
                        📢 Comentario Público (Visible al Solicitante)
                    </button>
                    <?php if (in_array($currentUser['role_name'], ['admin', 'manager', 'colaborador', 'agent', 'user'])): ?>
                    <button type="button" id="status-toggle-private" class="status-type-btn" onclick="setStatusNoteType(true)">
                        🔒 Nota Interna Privada
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="status-change-comment">Nota o Comentario del Cambio (Obligatorio):</label>
                <textarea id="status-change-comment" class="form-control" rows="3" placeholder="Explique la causa, avance o resolución de esta etapa..." required></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn-secondary close-status-modal">Cancelar</button>
                <button type="submit" class="btn-primary">✅ Guardar y Actualizar Estado</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL DE ENCUESTA DE SATISFACCIÓN (CSAT) - IDÉNTICA A LA IMAGEN ADJUNTA
     ==================================================================== -->
<div id="csat-modal" class="modal-backdrop" style="z-index: 1200;">
    <div class="csat-modal-card">
        <!-- TOP HEADER CON CSAT Y BADGE OBLIGATORIO -->
        <div class="csat-header-bar">
            <div class="csat-header-title">
                <span style="font-size:18px;">😄</span>
                <span>CSAT</span>
                <span style="color:#CBD5E1;">|</span>
                <span style="color:#64748B;">CSAT</span>
            </div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="csat-mandatory-badge">Obligatorio</span>
                <button class="close-modal close-csat-modal" style="font-size:18px; color:#94A3B8;">&times;</button>
            </div>
        </div>

        <form id="csat-form" class="csat-body">
            <input type="hidden" id="csat-ticket-id">
            <input type="hidden" id="csat-rating-value" value="5">

            <!-- PREGUNTA 1: CALIFICACIÓN CON 5 EMOJIS HORIZONTALES -->
            <h2 class="csat-question-title">
                ¿Qué tan satisfecho estás con tu experiencia de soporte el día de hoy?
            </h2>

            <div class="csat-emoji-grid">
                <!-- 1: Muy insatisfecho -->
                <div class="csat-emoji-btn" data-rating="1" onclick="selectCsatRating(1)">
                    <span class="emoji-icon">😢</span>
                    <span class="emoji-label">Muy Insatisfecho</span>
                </div>
                <!-- 2: Insatisfecho -->
                <div class="csat-emoji-btn" data-rating="2" onclick="selectCsatRating(2)">
                    <span class="emoji-icon">🙁</span>
                    <span class="emoji-label">Insatisfecho</span>
                </div>
                <!-- 3: Neutral -->
                <div class="csat-emoji-btn" data-rating="3" onclick="selectCsatRating(3)">
                    <span class="emoji-icon">😐</span>
                    <span class="emoji-label">Neutral</span>
                </div>
                <!-- 4: Satisfecho -->
                <div class="csat-emoji-btn" data-rating="4" onclick="selectCsatRating(4)">
                    <span class="emoji-icon">😊</span>
                    <span class="emoji-label">Satisfecho</span>
                </div>
                <!-- 5: Muy satisfecho -->
                <div class="csat-emoji-btn selected" data-rating="5" onclick="selectCsatRating(5)">
                    <span class="emoji-icon">🤩</span>
                    <span class="emoji-label">Muy Satisfecho</span>
                </div>
            </div>

            <p class="csat-subtext">Esperamos sus comentarios.</p>

            <div class="csat-divider">
                <div class="csat-divider-plus">+</div>
            </div>

            <!-- PREGUNTA 2: RESPUESTA LARGA DE TEXTO -->
            <div class="csat-comment-card">
                <div class="csat-comment-header">
                    <span>2 - 4</span>
                    <span>≡ Respuesta larga de texto</span>
                    <span>Q2</span>
                </div>
                <div class="csat-comment-prompt">
                    Gracias por tus comentarios. ¿Podrías detallar qué fue lo que más te gustó o qué podemos mejorar?
                </div>
                <textarea id="csat-feedback-text" class="form-control" rows="3" placeholder="Escriba aquí sus comentarios adicionales..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn-secondary close-csat-modal">Omitir por ahora</button>
                <button type="submit" class="btn-primary" style="background:#10B981; padding:0.65rem 1.4rem;">
                    🚀 Enviar Encuesta de Satisfacción
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@sgratzl/chartjs-chart-boxplot@4.2.0/build/index.umd.min.js"></script>
<script>
    window.dashboardCharts = {};

    function getBaseChartConfig(type, labels, data, labelName, colors = null) {
        // Standardize data for line, bar, doughnut based on user request to switch between them
        const bgColors = colors || ['#ef4444', '#f97316', '#3b82f6', '#10b981', '#6366f1', '#a855f7'];
        return {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: labelName,
                    data: data,
                    backgroundColor: type === 'line' ? 'rgba(59, 130, 246, 0.1)' : bgColors,
                    borderColor: type === 'line' ? '#3b82f6' : bgColors,
                    borderWidth: type === 'line' ? 2 : 0,
                    borderRadius: type === 'bar' ? 6 : 0,
                    fill: type === 'line'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: type === 'doughnut', position: 'right' }
                },
                scales: type === 'doughnut' ? {} : {
                    x: { grid: { display: false } },
                    y: { border: { dash: [4, 4] }, grid: { color: '#f1f5f9' }, beginAtZero: true }
                }
            }
        };
    }

    function renderAllCharts() {
        if (typeof Chart === 'undefined') return;
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#64748b";
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 4;

        const p1 = () => Math.round(Math.random() * 300);
        const p2 = () => Math.round(Math.random() * 200);

        // 1. Line Chart
        const ctxLine = document.getElementById('chartLine');
        if (ctxLine) {
            if(window.dashboardCharts['chartLine']) window.dashboardCharts['chartLine'].destroy();
            const cfg = getBaseChartConfig(document.querySelector("select[onchange=\"changeChartType('chartLine', this.value)\"]").value, 
                ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'], [p1(), p1(), p1(), p1(), p1(), p1(), p1()], 'Creados');
            window.dashboardCharts['chartLine'] = new Chart(ctxLine, cfg);
        }

        // 2. Bar Chart
        const ctxBar = document.getElementById('chartBar');
        if (ctxBar) {
            if(window.dashboardCharts['chartBar']) window.dashboardCharts['chartBar'].destroy();
            const cfg = getBaseChartConfig(document.querySelector("select[onchange=\"changeChartType('chartBar', this.value)\"]").value, 
                ['Laura', 'Carlos', 'Sofía', 'Miguel', 'Ana', 'Jorge'], [p2(), p2(), p2(), p2(), p2(), p2()], 'Resueltos', ['#3b82f6','#3b82f6','#3b82f6','#3b82f6','#3b82f6','#3b82f6']);
            window.dashboardCharts['chartBar'] = new Chart(ctxBar, cfg);
        }

        // 3. Doughnut Chart
        const ctxPie = document.getElementById('chartPie');
        if (ctxPie) {
            if(window.dashboardCharts['chartPie']) window.dashboardCharts['chartPie'].destroy();
            const cfg = getBaseChartConfig(document.querySelector("select[onchange=\"changeChartType('chartPie', this.value)\"]").value, 
                ['Crítica', 'Alta', 'Media', 'Baja'], [p2(), p2(), p2(), p2()], 'Prioridad', ['#ef4444', '#f97316', '#3b82f6', '#10b981']);
            window.dashboardCharts['chartPie'] = new Chart(ctxPie, cfg);
        }

        // 4. Histogram
        const ctxHist = document.getElementById('chartHist');
        if (ctxHist) {
            if(window.dashboardCharts['chartHist']) window.dashboardCharts['chartHist'].destroy();
            const cfg = getBaseChartConfig(document.querySelector("select[onchange=\"changeChartType('chartHist', this.value)\"]").value, 
                ['0-2h', '2-4h', '4-6h', '6-8h', '8-12h', '>12h'], [p1(), p1(), p1(), p1(), p1(), p1()], 'Frecuencia', ['#6366f1','#6366f1','#6366f1','#6366f1','#6366f1','#6366f1']);
            window.dashboardCharts['chartHist'] = new Chart(ctxHist, cfg);
        }

        // 5. Scatter Plot fallback (mapped to standardized standard charts for dynamic switching)
        const ctxScatter = document.getElementById('chartScatter');
        if (ctxScatter) {
            if(window.dashboardCharts['chartScatter']) window.dashboardCharts['chartScatter'].destroy();
            const cfg = getBaseChartConfig(document.querySelector("select[onchange=\"changeChartType('chartScatter', this.value)\"]").value, 
                ['T1', 'T2', 'T3', 'T4', 'T5'], [p2(), p2(), p2(), p2(), p2()], 'CSAT vs Tiempo');
            window.dashboardCharts['chartScatter'] = new Chart(ctxScatter, cfg);
        }

        // 6. Boxplot fallback (mapped to standard charts)
        const ctxBox = document.getElementById('chartBoxplot');
        if (ctxBox) {
            if(window.dashboardCharts['chartBoxplot']) window.dashboardCharts['chartBoxplot'].destroy();
            const cfg = getBaseChartConfig(document.querySelector("select[onchange=\"changeChartType('chartBoxplot', this.value)\"]").value, 
                ['Crítica', 'Alta', 'Media', 'Baja'], [p1(), p1(), p1(), p1()], 'Promedio Horas', ['#ef4444', '#f97316', '#3b82f6', '#10b981']);
            window.dashboardCharts['chartBoxplot'] = new Chart(ctxBox, cfg);
        }
    }

    function changeChartType(chartId, newType) {
        if (window.dashboardCharts[chartId]) {
            let chart = window.dashboardCharts[chartId];
            
            // Extract clean labels and data
            let labels = Array.isArray(chart.data.labels) ? Array.from(chart.data.labels) : [];
            let rawData = [];
            let labelName = chart.data.datasets[0].label || 'Data';
            
            // Handle complex types (Scatter/Boxplot) that don't map 1:1 to Bar/Line by regenerating dummy data for the visual
            if (chart.config.type === 'scatter' || chart.config.type === 'boxplot' || typeof chart.data.datasets[0].data[0] === 'object') {
                labels = ['A', 'B', 'C', 'D', 'E'];
                rawData = [120, 150, 90, 200, 180];
            } else {
                rawData = Array.from(chart.data.datasets[0].data);
            }
            
            chart.destroy();
            
            // Use the factory config to ensure completely clean state
            const cfg = getBaseChartConfig(newType, labels, rawData, labelName);
            // Remove animation for instant switching
            cfg.options.animation = false;
            
            window.dashboardCharts[chartId] = new Chart(document.getElementById(chartId), cfg);
        }
    }

    function simulateFilterUpdate() {
        // Render charts with new random demo data to simulate dynamic filtering updates
        renderAllCharts();
        
        // Add subtle animation to KPI cards to indicate refresh
        const cards = document.querySelectorAll('.fd-kpi-card');
        cards.forEach(c => {
            c.style.transform = 'scale(0.95)';
            c.style.opacity = '0.5';
            setTimeout(() => {
                c.style.transform = 'scale(1)';
                c.style.opacity = '1';
                // Update text randomly
                const numEl = c.querySelector('.fd-kpi-number') || c.querySelector('div[style*="font-size:2rem"]');
                if (numEl && numEl.textContent.includes('horas')) {
                    numEl.textContent = (Math.random() * 5 + 1).toFixed(1) + ' horas';
                } else if (numEl && !numEl.textContent.includes('%') && !numEl.textContent.includes('4.6')) {
                    numEl.textContent = Math.floor(Math.random() * 3000) + 1000;
                }
            }, 300);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderAllCharts();
    });
</script>
<script src="js/kanban.js"></script>
<script>
    let isInternalNoteMode = false;

    function openCreateTicketModal() {
        document.getElementById('create-ticket-modal').style.display = 'flex';
    }

    function closeCreateTicketModal() {
        document.getElementById('create-ticket-modal').style.display = 'none';
    }

    function closeTicketModal() {
        document.getElementById('ticket-modal').style.display = 'none';
    }

    function setCommentMode(isInternal) {
        isInternalNoteMode = isInternal;
        const btnPublic = document.getElementById('tab-reply-public');
        const btnPrivate = document.getElementById('tab-reply-private');
        const submitBtn = document.getElementById('btn-submit-comment');

        if (isInternal) {
            btnPublic.classList.remove('active');
            if (btnPrivate) btnPrivate.classList.add('active');
            submitBtn.textContent = '🔒 Guardar Nota Privada';
            submitBtn.style.backgroundColor = '#D97706';
        } else {
            btnPublic.classList.add('active');
            if (btnPrivate) btnPrivate.classList.remove('active');
            submitBtn.textContent = 'Enviar Respuesta';
            submitBtn.style.backgroundColor = 'var(--fd-action-btn)';
        }
    }

    function handleCommentSubmit() {
        submitComment(isInternalNoteMode);
    }

    async function updateTicketProperty(field, value) {
        const ticketId = document.getElementById('modal-ticket-id-input').value;
        if (!ticketId) return;

        try {
            const res = await fetch('api.php?action=update_ticket_properties', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: JSON.stringify({
                    ticket_id: parseInt(ticketId),
                    [field]: field === 'status_id' ? parseInt(value) : value,
                    csrf_token: window.CSRF_TOKEN
                })
            });
            const result = await res.json();
            if (result.success) {
                showToast(result.message, 'success');
                if (field === 'priority') {
                    const prioPill = document.getElementById('modal-ticket-prio-pill');
                    if (prioPill) {
                        prioPill.textContent = value;
                        prioPill.className = `fd-prio-badge fd-prio-${value}`;
                    }
                    document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(row => {
                        row.dataset.priority = value;
                        const badge = row.querySelector('.fd-prio-badge');
                        if (badge) {
                            badge.textContent = value;
                            badge.className = `fd-prio-badge fd-prio-${value}`;
                        }
                    });
                    document.querySelectorAll(`.ticket-card[data-ticket-id="${ticketId}"]`).forEach(card => {
                        const badge = card.querySelector('.fd-prio-badge');
                        if (badge) {
                            badge.textContent = value;
                            badge.className = `fd-prio-badge fd-prio-${value}`;
                        }
                    });
                    if (typeof currentTicketData !== 'undefined' && currentTicketData && parseInt(currentTicketData.id) === parseInt(ticketId)) {
                        currentTicketData.priority = value;
                    }
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Error al actualizar propiedad.', 'error');
        }
    }

    function switchTicketsView(view) {
        const listDiv = document.getElementById('tickets-subview-list');
        const kanbanDiv = document.getElementById('tickets-subview-kanban');
        const btnList = document.getElementById('btn-toggle-list');
        const btnKanban = document.getElementById('btn-toggle-kanban');

        if (view === 'list') {
            listDiv.style.display = 'block';
            kanbanDiv.style.display = 'none';
            btnList.classList.add('active');
            btnKanban.classList.remove('active');
        } else {
            listDiv.style.display = 'none';
            kanbanDiv.style.display = 'block';
            btnKanban.classList.add('active');
            btnList.classList.remove('active');
        }
    }

    function switchNavTab(tabId) {
        const tabs = document.querySelectorAll('.nav-tab');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(t => {
            if (t.dataset.target === tabId) t.classList.add('active');
            else t.classList.remove('active');
        });

        contents.forEach(c => {
            if (c.id === tabId) c.classList.add('active');
            else c.classList.remove('active');
        });

        if (tabId === 'tickets-tab') {
            const heading = document.getElementById('tickets-view-heading');
            const titleSpan = heading ? heading.querySelector('span:first-child') : null;
            if (titleSpan) titleSpan.textContent = 'Todos los tickets';
            else if (heading) heading.textContent = 'Todos los tickets';
            const resetBtn = document.getElementById('reset-tickets-filter');
            if (resetBtn) resetBtn.style.display = 'none';
            document.querySelectorAll('#all-tickets-tbody tr.ticket-row').forEach(r => r.style.display = '');
            const emptyRow = document.getElementById('all-tickets-empty-search');
            if (emptyRow) emptyRow.style.display = 'none';
        }

        if (tabId === 'inbox-tab' && typeof loadUnassignedTickets === 'function') {
            loadUnassignedTickets();
        }
    }

    function filterAndShowTickets(filterType) {
        switchNavTab('tickets-tab');
        switchTicketsView('list');

        const rows = document.querySelectorAll('#all-tickets-tbody tr.ticket-row');
        const heading = document.getElementById('tickets-view-heading');
        const resetBtn = document.getElementById('reset-tickets-filter');
        const titleSpan = heading ? heading.querySelector('span:first-child') : null;

        if (filterType === 'all') {
            if (titleSpan) titleSpan.textContent = 'Todos los tickets';
            if (resetBtn) resetBtn.style.display = 'none';
            rows.forEach(r => r.style.display = '');
            return;
        }

        if (resetBtn) resetBtn.style.display = 'inline-flex';

        if (filterType === 'unresolved') {
            if (titleSpan) titleSpan.textContent = 'Tickets no resueltos';
            rows.forEach(r => {
                const statusId = parseInt(r.dataset.statusId);
                r.style.display = (statusId !== 4) ? '' : 'none';
            });
        } else if (filterType === 'overdue' || filterType === 'due_today') {
            if (titleSpan) titleSpan.textContent = 'Tickets que vencen hoy o prioritarios';
            rows.forEach(r => {
                const prio = r.dataset.priority;
                r.style.display = (prio === 'urgent' || prio === 'high') ? '' : 'none';
            });
        } else if (filterType === 'open') {
            if (titleSpan) titleSpan.textContent = 'Tickets abiertos';
            rows.forEach(r => {
                const statusId = parseInt(r.dataset.statusId);
                r.style.display = (statusId === 2) ? '' : 'none';
            });
        } else if (filterType === 'waiting') {
            if (titleSpan) titleSpan.textContent = 'Tickets en espera de usuario';
            rows.forEach(r => {
                const statusId = parseInt(r.dataset.statusId);
                r.style.display = (statusId === 3) ? '' : 'none';
            });
        } else if (filterType === 'unassigned') {
            if (titleSpan) titleSpan.textContent = 'Tickets sin asignar';
            rows.forEach(r => {
                const agentId = parseInt(r.dataset.agentId);
                r.style.display = (!agentId || agentId === 0) ? '' : 'none';
            });
        }
    }

    function filterAreaChange(areaId) {
        const activeContent = document.querySelector('.tab-content.active');
        const activeTab = activeContent ? activeContent.id : 'dashboard-tab';
        window.location.href = 'index.php?area_id=' + encodeURIComponent(areaId) + '&tab=' + encodeURIComponent(activeTab);
    }

    async function logout() {
        await fetch('api.php?action=logout');
        window.location.href = 'login.php';
    }
</script>
</body>
</html>

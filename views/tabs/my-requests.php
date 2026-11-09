<?php
/**
 * Vista de Mis Tickets y Solicitudes
 * Divide claramente:
 * 1. Mis Tickets Asignados (Como Agente / Responsable)
 * 2. Solicitudes Creadas por Mí (Como Solicitante)
 * Incluye Métricas (KPIs), Filtros Operativos y Paginación idéntica a la Mesa de Ayuda
 */

$myAssigned = $myAssignedTickets ?? [];
$myReq = $myRequests ?? [];

// Métricas de Asignados
$countAssignedTotal = count($myAssigned);
$countAssignedOpen = count(array_filter($myAssigned, function($t) {
    $sId = (int)($t['status_id'] ?? 1);
    $sp = mb_strtolower(trim($t['sp_status'] ?? ''));
    return in_array($sId, [1, 2], true) || in_array($sp, ['abierto', 'en proceso'], true);
}));
$countAssignedStandBy = count(array_filter($myAssigned, function($t) {
    $sId = (int)($t['status_id'] ?? 1);
    $sp = mb_strtolower(trim($t['sp_status'] ?? ''));
    return $sId === 3 || $sp === 'stand by';
}));
$countAssignedResolved = count(array_filter($myAssigned, function($t) {
    $sId = (int)($t['status_id'] ?? 1);
    $sp = mb_strtolower(trim($t['sp_status'] ?? ''));
    return $sId === 4 || $sp === 'procesado';
}));

// Métricas de Solicitados
$countReqTotal = count($myReq);
$countReqOpen = count(array_filter($myReq, fn($t) => in_array((int)($t['status_id'] ?? 1), [1, 2], true)));
$countReqStandBy = count(array_filter($myReq, fn($t) => (int)($t['status_id'] ?? 1) === 3));
$countReqResolved = count(array_filter($myReq, fn($t) => (int)($t['status_id'] ?? 1) === 4));
?>

<section id="my-requests-tab" class="tab-content <?= ($activeTab === 'my-requests-tab') ? 'active' : '' ?>">
    <div class="fd-view-header">
        <div class="fd-view-title">
            <h1>Mis Tickets & Solicitudes</h1>
            <p>Panel unificado de tickets asignados a tu responsabilidad y requerimientos creados por ti</p>
        </div>

        <div class="fd-header-actions" style="display:flex; gap:0.5rem; align-items:center;">
            <button class="btn-primary" onclick="openCreateTicketModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nueva Solicitud
            </button>
        </div>
    </div>

    <!-- 1. PESTAÑAS SEGMENTADAS (SUB-TABS) -->
    <div class="fd-my-subtabs-bar">
        <button type="button" id="subtab-btn-assigned" class="fd-my-subtab-btn active" onclick="switchMyTicketsSubTab('assigned')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 11 12 14 22 4"></polyline>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg>
            <span>Mis Tickets Asignados (Responsable)</span>
            <span class="fd-subtab-badge" id="badge-count-assigned"><?= $countAssignedTotal ?></span>
        </button>

        <button type="button" id="subtab-btn-requested" class="fd-my-subtab-btn" onclick="switchMyTicketsSubTab('requested')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span>Solicitudes Creadas por Mí (Solicitante)</span>
            <span class="fd-subtab-badge" id="badge-count-requested"><?= $countReqTotal ?></span>
        </button>
    </div>

    <!-- 2. MÉTRICAS DINÁMICAS (KPIS) -->
    <div class="fd-kpi-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.25rem;">
        <div class="fd-kpi-card unresolved" onclick="setQuickMyStatusFilter('')" style="cursor:pointer;" title="Ver todos los tickets">
            <div class="fd-kpi-label" id="kpi-lbl-total">Total Asignados</div>
            <div class="fd-kpi-number" id="kpi-num-total"><?= $countAssignedTotal ?></div>
            <div class="fd-kpi-subtext" id="kpi-sub-total">Tickets a tu cargo</div>
        </div>

        <div class="fd-kpi-card open" onclick="setQuickMyStatusFilter('en proceso')" style="cursor:pointer;" title="Filtrar tickets en gestión">
            <div class="fd-kpi-label" style="color:#0284C7;">En Gestión / Abiertos</div>
            <div class="fd-kpi-number" style="color:#0284C7;" id="kpi-num-open"><?= $countAssignedOpen ?></div>
            <div class="fd-kpi-subtext">Requieren avance</div>
        </div>

        <div class="fd-kpi-card due-today" onclick="setQuickMyStatusFilter('stand by')" style="cursor:pointer;" title="Filtrar tickets en espera">
            <div class="fd-kpi-label" style="color:#EA580C;">En Espera / Stand By</div>
            <div class="fd-kpi-number" style="color:#EA580C;" id="kpi-num-waiting"><?= $countAssignedStandBy ?></div>
            <div class="fd-kpi-subtext">Detenidos temporalmente</div>
        </div>

        <div class="fd-kpi-card resolved" onclick="setQuickMyStatusFilter('procesado')" style="cursor:pointer;" title="Filtrar tickets resueltos">
            <div class="fd-kpi-label" style="color:#10B981;">Resueltos / Procesados</div>
            <div class="fd-kpi-number" style="color:#10B981;" id="kpi-num-resolved"><?= $countAssignedResolved ?></div>
            <div class="fd-kpi-subtext">Casos finalizados</div>
        </div>
    </div>

    <!-- 3. BARRA DE FILTROS EN VIVO -->
    <div class="fd-user-filter-toolbar" style="margin-bottom: 1rem;">
        <div class="fd-user-filter-inputs" style="flex-wrap: wrap;">
            <!-- Buscador en tiempo real -->
            <div class="fd-user-search-wrap" style="min-width: 260px;">
                <svg class="fd-user-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="my-tickets-search" placeholder="Buscar por código, asunto, solicitante..." oninput="onMyTicketsFilterChange()">
            </div>

            <!-- Filtro de Estado -->
            <select id="my-tickets-filter-status" class="fd-user-select" onchange="onMyTicketsFilterChange()">
                <option value="">Todos los Estados</option>
                <option value="en proceso">Abiertos / En Proceso</option>
                <option value="stand by">En Espera / Stand By</option>
                <option value="procesado">Resueltos / Procesados</option>
            </select>

            <!-- Filtro de Prioridad -->
            <select id="my-tickets-filter-priority" class="fd-user-select" onchange="onMyTicketsFilterChange()">
                <option value="">Todas las Prioridades</option>
                <option value="urgent">Crítica / Urgente</option>
                <option value="high">Alta</option>
                <option value="medium">Media</option>
                <option value="low">Baja</option>
            </select>

            <!-- Filtro de Departamento / Área -->
            <select id="my-tickets-filter-area" class="fd-user-select" onchange="onMyTicketsFilterChange()">
                <option value="">Todos los Departamentos</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= htmlspecialchars(strtolower($a['name'])) ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="button" class="btn-secondary" style="font-size:12px; padding:0.4rem 0.75rem;" onclick="resetMyTicketsFilters()">
                Limpiar Filtros
            </button>
        </div>
    </div>

    <!-- ====================================================================
         SECCIÓN A: MIS TICKETS ASIGNADOS (COMO AGENTE / RESPONSABLE)
         ==================================================================== -->
    <div id="my-assigned-view" class="fd-my-section-view">
        <div class="fd-table-card">
            <div class="fd-table-header">
                <div>
                    <h3 style="margin:0; font-size:14px; font-weight:700;">Tickets Asignados a mi Gestión</h3>
                    <span style="font-size:11.5px; color:var(--fd-text-muted);">Tickets donde figuras como responsable directo o asignado</span>
                </div>
                <span class="badge" style="background:#EFF6FF; color:#2563EB; font-weight:700;" id="assigned-table-badge"><?= $countAssignedTotal ?> tickets</span>
            </div>

            <table class="fd-table" id="my-assigned-table">
                <thead>
                    <tr>
                        <th>Código y Asunto</th>
                        <th>Empresa / Área</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Solicitante</th>
                        <th>Fecha Creación</th>
                        <th>Vencimiento SLA</th>
                        <th style="text-align:right;">Acción</th>
                    </tr>
                </thead>
                <tbody id="my-assigned-tbody">
                    <?php if (empty($myAssigned)): ?>
                        <tr class="empty-placeholder-row">
                            <td colspan="8" style="text-align:center; padding:3rem 1rem; color:var(--fd-text-muted);">
                                <div style="font-size:2rem; margin-bottom:0.5rem;">🎉</div>
                                <div style="font-size:15px; font-weight:700; color:var(--fd-text);">¡Estás al día!</div>
                                <p style="font-size:12.5px; margin:0.35rem 0 1rem 0;">No tienes tickets pendientes asignados a tu nombre en este momento.</p>
                                <button type="button" class="btn-secondary" onclick="switchNavTab('tickets-tab')">Explorar Bandeja General</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myAssigned as $t): 
                            $statusId = (int)($t['status_id'] ?? 1);
                            $spStatus = mb_strtolower(trim($t['sp_status'] ?? ''));
                            $statusSlug = mb_strtolower(trim($t['status_slug'] ?? 'open'));
                            $priority = strtolower(trim($t['priority'] ?? 'medium'));
                            $prioClass = 'prio-' . $priority;
                            $prioLabel = ucfirst($priority);
                            if ($prioLabel === 'Urgent') $prioLabel = 'Crítica';
                            if ($prioLabel === 'High') $prioLabel = 'Alta';
                            if ($prioLabel === 'Medium') $prioLabel = 'Media';
                            if ($prioLabel === 'Low') $prioLabel = 'Baja';

                            $displayStatus = !empty($t['sp_status']) ? $t['sp_status'] : ($t['status_name'] ?? 'En Proceso');
                        ?>
                            <tr class="ticket-row my-ticket-row" 
                                data-ticket-id="<?= (int)$t['id'] ?>"
                                data-id="<?= (int)$t['id'] ?>"
                                data-timestamp="<?= strtotime($t['created_at'] ?? '2000-01-01 00:00:00') ?>"
                                data-code="<?= htmlspecialchars(strtolower($t['code'])) ?>"
                                data-title="<?= htmlspecialchars(strtolower($t['title'])) ?>"
                                data-company="<?= htmlspecialchars(strtolower($t['company_name'] ?? '')) ?>"
                                data-area="<?= htmlspecialchars(strtolower($t['area_name'] ?? '')) ?>"
                                data-priority="<?= htmlspecialchars($priority) ?>"
                                data-status-id="<?= $statusId ?>"
                                data-status-slug="<?= htmlspecialchars($statusSlug) ?>"
                                data-sp-status="<?= htmlspecialchars($spStatus) ?>"
                                data-person="<?= htmlspecialchars(strtolower($t['requester_name'] ?? '')) ?>">
                                <td>
                                    <span class="fd-ticket-code"><?= htmlspecialchars($t['code']) ?></span>
                                    <a class="fd-ticket-link" href="javascript:void(0)" onclick="if(typeof openTicketModal==='function'){openTicketModal(<?= (int)$t['id'] ?>);}">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <span style="font-weight:600; font-size:12px; color:var(--fd-text);"><?= htmlspecialchars($t['company_name'] ?? 'Kaizen') ?></span>
                                        <span class="fd-portal-badge" style="font-size:10.5px; align-self:flex-start;"><?= htmlspecialchars($t['area_name'] ?? 'General') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $prioClass ?>" style="font-size:11px; padding:2px 7px;">
                                         <?= htmlspecialchars($prioLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fd-status-badge fd-status-<?= htmlspecialchars($statusSlug) ?>">
                                        <?= htmlspecialchars($displayStatus) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600; font-size:12px; color:var(--fd-text);">
                                        <?= htmlspecialchars($t['requester_name'] ?? 'Empresa') ?>
                                    </span>
                                    <?php if (!empty($t['requester_email'])): ?>
                                        <div style="font-size:11px; color:var(--fd-text-muted);"><?= htmlspecialchars($t['requester_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:var(--fd-text-muted);">
                                    <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                                </td>
                                <td style="font-size:12px;">
                                    <?php if (!empty($t['due_date'])): ?>
                                        <span style="color:#D97706; font-weight:600;">⏰ <?= date('d/m/Y', strtotime($t['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--fd-text-muted); font-size:11.5px;">SLA Estándar</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:0.35rem; align-items:center;">
                                        <button type="button" class="btn-secondary" style="padding:0.3rem 0.65rem; font-size:11px;" onclick="event.stopPropagation(); if(typeof openTicketModal==='function'){openTicketModal(<?= (int)$t['id'] ?>);}">
                                            Gestionar
                                        </button>
                                        <?php 
                                            $canDeleteAssigned = ($role === 'admin') || ($role === 'manager') || in_array($role, ['colaborador', 'agent'], true);
                                        ?>
                                        <?php if ($canDeleteAssigned): ?>
                                            <button type="button" class="btn-secondary" style="padding:0.3rem 0.5rem; font-size:11px; color:#DC2626; border-color:#FECACA; background:#FEF2F2;" onclick="event.stopPropagation(); deleteTicket(<?= (int)$t['id'] ?>, '<?= htmlspecialchars($t['code'], ENT_QUOTES) ?>', <?= !empty($t['sharepoint_id']) ? (int)$t['sharepoint_id'] : 0 ?>)" title="Eliminar Ticket (App y SharePoint)">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- BARRA DE PAGINACIÓN DE TICKETS ASIGNADOS -->
            <div class="fd-pagination-bar" id="assigned-pagination-bar" style="display:flex; justify-content:space-between; align-items:center; padding:0.9rem 1.5rem; background:#F8FAFC; border-top:1px solid #E2E8F0; font-size:13px; color:#64748B; flex-wrap:wrap; gap:0.75rem;">
                <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                    <span>Mostrando <strong id="assigned-pag-start" style="color:#0F172A;">1</strong> a <strong id="assigned-pag-end" style="color:#0F172A;">20</strong> de <strong id="assigned-pag-total" style="color:#0F172A;"><?= $countAssignedTotal ?></strong> tickets</span>
                    <span style="color:#CBD5E1;">|</span>
                    <span style="font-size:12px; color:#64748B;">Página <strong id="assigned-pag-current" style="color:#0284C7;">1</strong> de <strong id="assigned-pag-pages">1</strong></span>
                    <span style="color:#CBD5E1;">|</span>
                    <label style="font-size:12px; color:#64748B; display:inline-flex; align-items:center; gap:0.4rem;">
                        <span>Mostrar:</span>
                        <select id="assigned-page-size-select" onchange="onAssignedPageSizeChange(this.value)" style="padding:0.25rem 0.5rem; font-size:12px; font-weight:600; color:#0F172A; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:4px; cursor:pointer;" title="Tickets por página">
                            <option value="10">10 por pág.</option>
                            <option value="20" selected>20 por pág.</option>
                            <option value="50">50 por pág.</option>
                            <option value="100">100 por pág.</option>
                        </select>
                    </label>
                </div>
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    <button type="button" class="btn-secondary" id="assigned-btn-prev" onclick="changeAssignedPage(-1)" style="padding:0.35rem 0.75rem; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Anterior
                    </button>
                    <div id="assigned-pagination-pages-container" style="display:flex; gap:0.25rem;"></div>
                    <button type="button" class="btn-secondary" id="assigned-btn-next" onclick="changeAssignedPage(1)" style="padding:0.35rem 0.75rem; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;">
                        Siguiente <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         SECCIÓN B: SOLICITUDES CREADAS POR MÍ (COMO SOLICITANTE)
         ==================================================================== -->
    <div id="my-requested-view" class="fd-my-section-view" style="display:none;">
        <div class="fd-table-card">
            <div class="fd-table-header">
                <div>
                    <h3 style="margin:0; font-size:14px; font-weight:700;">Requerimientos Creados por Mí</h3>
                    <span style="font-size:11.5px; color:var(--fd-text-muted);">Tickets que has solicitado para soporte de cualquier departamento</span>
                </div>
                <span class="badge" style="background:#F5F3FF; color:#6D28D9; font-weight:700;" id="requested-table-badge"><?= $countReqTotal ?> solicitudes</span>
            </div>

            <table class="fd-table" id="my-requested-table">
                <thead>
                    <tr>
                        <th>Código y Asunto</th>
                        <th>Departamento Destino</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Agente Responsable</th>
                        <th>Fecha Solicitud</th>
                        <th>Calificación de Servicio</th>
                        <th style="text-align:right;">Acción</th>
                    </tr>
                </thead>
                <tbody id="my-requested-tbody">
                    <?php if (empty($myReq)): ?>
                        <tr class="empty-placeholder-row">
                            <td colspan="8" style="text-align:center; padding:3rem 1rem; color:var(--fd-text-muted);">
                                <div style="font-size:2rem; margin-bottom:0.5rem;">📝</div>
                                <div style="font-size:15px; font-weight:700; color:var(--fd-text);">No has creado solicitudes de soporte aún</div>
                                <p style="font-size:12.5px; margin:0.35rem 0 1rem 0;">Cuando requieras asistencia de TI, Servicios Generales u otra área, presiona el botón de abajo.</p>
                                <button type="button" class="btn-primary" onclick="openCreateTicketModal()">+ Crear Primera Solicitud</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myReq as $mr): 
                            $statusId = (int)($mr['status_id'] ?? 1);
                            $spStatus = mb_strtolower(trim($mr['sp_status'] ?? ''));
                            $statusSlug = mb_strtolower(trim($mr['status_slug'] ?? 'open'));
                            $priority = strtolower(trim($mr['priority'] ?? 'medium'));
                            $prioClass = 'prio-' . $priority;
                            $prioLabel = ucfirst($priority);
                            if ($prioLabel === 'Urgent') $prioLabel = 'Crítica';
                            if ($prioLabel === 'High') $prioLabel = 'Alta';
                            if ($prioLabel === 'Medium') $prioLabel = 'Media';
                            if ($prioLabel === 'Low') $prioLabel = 'Baja';

                            $agentDisplay = !empty($mr['agent_name']) ? $mr['agent_name'] : (!empty($mr['responsable']) ? $mr['responsable'] : '');
                        ?>
                            <tr class="ticket-row my-ticket-row" 
                                data-ticket-id="<?= (int)$mr['id'] ?>"
                                data-id="<?= (int)$mr['id'] ?>"
                                data-timestamp="<?= strtotime($mr['created_at'] ?? '2000-01-01 00:00:00') ?>"
                                data-code="<?= htmlspecialchars(strtolower($mr['code'])) ?>"
                                data-title="<?= htmlspecialchars(strtolower($mr['title'])) ?>"
                                data-company="<?= htmlspecialchars(strtolower($mr['company_name'] ?? '')) ?>"
                                data-area="<?= htmlspecialchars(strtolower($mr['area_name'] ?? '')) ?>"
                                data-priority="<?= htmlspecialchars($priority) ?>"
                                data-status-id="<?= $statusId ?>"
                                data-status-slug="<?= htmlspecialchars($statusSlug) ?>"
                                data-sp-status="<?= htmlspecialchars($spStatus) ?>"
                                data-person="<?= htmlspecialchars(strtolower($agentDisplay)) ?>">
                                <td>
                                    <span class="fd-ticket-code"><?= htmlspecialchars($mr['code']) ?></span>
                                    <a class="fd-ticket-link" href="javascript:void(0)" onclick="if(typeof openTicketModal==='function'){openTicketModal(<?= (int)$mr['id'] ?>);}">
                                        <?= htmlspecialchars($mr['title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="fd-portal-badge" style="font-size:11px;"><?= htmlspecialchars($mr['area_name'] ?? 'General') ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $prioClass ?>" style="font-size:11px; padding:2px 7px;">
                                        <?= htmlspecialchars($prioLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fd-status-badge fd-status-<?= htmlspecialchars($statusSlug) ?>">
                                        <?= htmlspecialchars($mr['status_name'] ?? 'Por Asignar') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="agent-name-display" style="font-size:12px; font-weight:600; color:var(--fd-text);">
                                        <?= !empty($agentDisplay) ? htmlspecialchars($agentDisplay) : '<em style="color:#94A3B8; font-weight:400;">En espera de asignación</em>' ?>
                                    </span>
                                </td>
                                <td style="font-size:12px; color:var(--fd-text-muted);">
                                    <?= date('d/m/Y H:i', strtotime($mr['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($statusId === 4 || $spStatus === 'procesado'): ?>
                                        <?php if (!empty($mr['csat_rating'])): ?>
                                            <span style="display:inline-flex; align-items:center; gap:0.3rem; background:#DCFCE7; color:#166534; padding:0.25rem 0.6rem; border-radius:12px; font-size:11px; font-weight:700;">
                                                ⭐ <?= (int)$mr['csat_rating'] ?>/5 Calificado
                                            </span>
                                        <?php else: ?>
                                            <button type="button" class="btn-primary" style="background:#D97706; padding:0.25rem 0.55rem; font-size:11px;" onclick="event.stopPropagation(); openCsatModal(<?= (int)$mr['id'] ?>, '<?= htmlspecialchars($mr['code']) ?>')">
                                                ⭐ Calificar
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--fd-text-muted); font-size:11.5px;">En atención...</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:0.35rem; align-items:center;">
                                        <button type="button" class="btn-secondary" style="padding:0.3rem 0.65rem; font-size:11px;" onclick="event.stopPropagation(); if(typeof openTicketModal==='function'){openTicketModal(<?= (int)$mr['id'] ?>);}">
                                            Detalle
                                        </button>
                                        <?php if ($role === 'admin' || $role === 'manager' || in_array($role, ['colaborador', 'agent'], true) || $statusId === 1): ?>
                                            <button type="button" class="btn-secondary" style="padding:0.3rem 0.5rem; font-size:11px; color:#DC2626; border-color:#FECACA; background:#FEF2F2;" onclick="event.stopPropagation(); deleteTicket(<?= (int)$mr['id'] ?>, '<?= htmlspecialchars($mr['code'], ENT_QUOTES) ?>', <?= !empty($mr['sharepoint_id']) ? (int)$mr['sharepoint_id'] : 0 ?>)" title="Eliminar Solicitud (App y SharePoint)">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- BARRA DE PAGINACIÓN DE SOLICITUDES CREADAS -->
            <div class="fd-pagination-bar" id="requested-pagination-bar" style="display:flex; justify-content:space-between; align-items:center; padding:0.9rem 1.5rem; background:#F8FAFC; border-top:1px solid #E2E8F0; font-size:13px; color:#64748B; flex-wrap:wrap; gap:0.75rem;">
                <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                    <span>Mostrando <strong id="requested-pag-start" style="color:#0F172A;">1</strong> a <strong id="requested-pag-end" style="color:#0F172A;">20</strong> de <strong id="requested-pag-total" style="color:#0F172A;"><?= $countReqTotal ?></strong> solicitudes</span>
                    <span style="color:#CBD5E1;">|</span>
                    <span style="font-size:12px; color:#64748B;">Página <strong id="requested-pag-current" style="color:#0284C7;">1</strong> de <strong id="requested-pag-pages">1</strong></span>
                    <span style="color:#CBD5E1;">|</span>
                    <label style="font-size:12px; color:#64748B; display:inline-flex; align-items:center; gap:0.4rem;">
                        <span>Mostrar:</span>
                        <select id="requested-page-size-select" onchange="onRequestedPageSizeChange(this.value)" style="padding:0.25rem 0.5rem; font-size:12px; font-weight:600; color:#0F172A; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:4px; cursor:pointer;" title="Solicitudes por página">
                            <option value="10">10 por pág.</option>
                            <option value="20" selected>20 por pág.</option>
                            <option value="50">50 por pág.</option>
                            <option value="100">100 por pág.</option>
                        </select>
                    </label>
                </div>
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    <button type="button" class="btn-secondary" id="requested-btn-prev" onclick="changeRequestedPage(-1)" style="padding:0.35rem 0.75rem; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Anterior
                    </button>
                    <div id="requested-pagination-pages-container" style="display:flex; gap:0.25rem;"></div>
                    <button type="button" class="btn-secondary" id="requested-btn-next" onclick="changeRequestedPage(1)" style="padding:0.35rem 0.75rem; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;">
                        Siguiente <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Lógica de Sub-Pestañas, Filtros y Paginación en "Mis Tickets & Solicitudes"
(function() {
    let currentSubTab = 'assigned';

    // Recuperar tamaño de página preferido (por defecto 20)
    let savedPageSize = parseInt(localStorage.getItem('my_tickets_page_size') || '20', 10);
    if (isNaN(savedPageSize) || savedPageSize <= 0) savedPageSize = 20;

    // Estados de paginación independientes
    const paginationState = {
        assigned: {
            page: 1,
            pageSize: savedPageSize
        },
        requested: {
            page: 1,
            pageSize: savedPageSize
        }
    };

    const metricsData = {
        assigned: {
            total: <?= $countAssignedTotal ?>,
            open: <?= $countAssignedOpen ?>,
            waiting: <?= $countAssignedStandBy ?>,
            resolved: <?= $countAssignedResolved ?>,
            subTotal: 'Tickets a tu cargo'
        },
        requested: {
            total: <?= $countReqTotal ?>,
            open: <?= $countReqOpen ?>,
            waiting: <?= $countReqStandBy ?>,
            resolved: <?= $countReqResolved ?>,
            subTotal: 'Solicitadas por ti'
        }
    };

    window.switchMyTicketsSubTab = function(tab) {
        currentSubTab = tab;

        const btnAssigned = document.getElementById('subtab-btn-assigned');
        const btnRequested = document.getElementById('subtab-btn-requested');
        const viewAssigned = document.getElementById('my-assigned-view');
        const viewRequested = document.getElementById('my-requested-view');

        if (tab === 'assigned') {
            btnAssigned.classList.add('active');
            btnRequested.classList.remove('active');
            viewAssigned.style.display = 'block';
            viewRequested.style.display = 'none';
        } else {
            btnRequested.classList.add('active');
            btnAssigned.classList.remove('active');
            viewRequested.style.display = 'block';
            viewAssigned.style.display = 'none';
        }

        // Actualizar tarjetas de KPI
        updateMyTicketsKPIs(tab);

        // Aplicar filtros y paginar la tabla activa
        renderCurrentTablePagination();
    };

    function updateMyTicketsKPIs(tab) {
        const d = metricsData[tab] || metricsData.assigned;
        const numTotal = document.getElementById('kpi-num-total');
        const numOpen = document.getElementById('kpi-num-open');
        const numWaiting = document.getElementById('kpi-num-waiting');
        const numResolved = document.getElementById('kpi-num-resolved');
        const lblTotal = document.getElementById('kpi-lbl-total');
        const subTotal = document.getElementById('kpi-sub-total');

        if (numTotal) numTotal.innerText = d.total;
        if (numOpen) numOpen.innerText = d.open;
        if (numWaiting) numWaiting.innerText = d.waiting;
        if (numResolved) numResolved.innerText = d.resolved;
        if (lblTotal) lblTotal.innerText = (tab === 'assigned') ? 'Total Asignados' : 'Total Solicitadas';
        if (subTotal) subTotal.innerText = d.subTotal;
    }

    // Disparador de filtros (reinicia a página 1)
    window.onMyTicketsFilterChange = function() {
        paginationState[currentSubTab].page = 1;
        renderCurrentTablePagination();
    };

    window.onAssignedPageSizeChange = function(newSize) {
        const val = parseInt(newSize, 10) || 20;
        paginationState.assigned.pageSize = val;
        paginationState.requested.pageSize = val;
        try { localStorage.setItem('my_tickets_page_size', String(val)); } catch(e){}
        const reqSelect = document.getElementById('requested-page-size-select');
        if (reqSelect) reqSelect.value = String(val);
        paginationState.assigned.page = 1;
        renderCurrentTablePagination();
    };

    window.onRequestedPageSizeChange = function(newSize) {
        const val = parseInt(newSize, 10) || 20;
        paginationState.requested.pageSize = val;
        paginationState.assigned.pageSize = val;
        try { localStorage.setItem('my_tickets_page_size', String(val)); } catch(e){}
        const assSelect = document.getElementById('assigned-page-size-select');
        if (assSelect) assSelect.value = String(val);
        paginationState.requested.page = 1;
        renderCurrentTablePagination();
    };

    window.changeAssignedPage = function(delta) {
        paginationState.assigned.page = Math.max(1, paginationState.assigned.page + delta);
        renderCurrentTablePagination();
        document.getElementById('my-assigned-view')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.changeRequestedPage = function(delta) {
        paginationState.requested.page = Math.max(1, paginationState.requested.page + delta);
        renderCurrentTablePagination();
        document.getElementById('my-requested-view')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Renderiza filtros y paginación para la sub-pestaña actual
    function renderCurrentTablePagination() {
        const query = (document.getElementById('my-tickets-search')?.value || '').toLowerCase().trim();
        const statusVal = (document.getElementById('my-tickets-filter-status')?.value || '').toLowerCase().trim();
        const prioVal = (document.getElementById('my-tickets-filter-priority')?.value || '').toLowerCase().trim();
        const areaVal = (document.getElementById('my-tickets-filter-area')?.value || '').toLowerCase().trim();

        const isAssigned = (currentSubTab === 'assigned');
        const tableId = isAssigned ? 'my-assigned-table' : 'my-requested-table';
        const prefix = isAssigned ? 'assigned' : 'requested';
        const state = paginationState[currentSubTab];

        const allRows = document.querySelectorAll(`#${tableId} tbody tr.my-ticket-row`);
        const matchedRows = [];

        allRows.forEach(r => {
            const code = r.dataset.code || '';
            const title = r.dataset.title || '';
            const company = r.dataset.company || '';
            const person = r.dataset.person || '';
            const area = r.dataset.area || '';
            const priority = r.dataset.priority || '';
            const statusId = parseInt(r.dataset.statusId || '1', 10);
            const spStatus = r.dataset.spStatus || '';

            const matchQuery = !query || 
                code.includes(query) || 
                title.includes(query) || 
                company.includes(query) || 
                person.includes(query);

            const matchPriority = !prioVal || priority === prioVal;
            const matchArea = !areaVal || area.includes(areaVal);

            let matchStatus = true;
            if (statusVal === 'en proceso') {
                matchStatus = (statusId === 1 || statusId === 2 || spStatus === 'abierto' || spStatus === 'en proceso');
            } else if (statusVal === 'stand by') {
                matchStatus = (statusId === 3 || spStatus === 'stand by');
            } else if (statusVal === 'procesado') {
                matchStatus = (statusId === 4 || spStatus === 'procesado');
            }

            if (matchQuery && matchPriority && matchArea && matchStatus) {
                matchedRows.push(r);
            } else {
                r.style.display = 'none';
            }
        });

        // Ordenar del más nuevo al más antiguo (created_at DESC, id DESC)
        matchedRows.sort((a, b) => {
            const timeA = parseInt(a.dataset.timestamp || '0', 10) || 0;
            const timeB = parseInt(b.dataset.timestamp || '0', 10) || 0;
            if (timeB !== timeA) return timeB - timeA;
            const idA = parseInt(a.dataset.id || '0', 10) || 0;
            const idB = parseInt(b.dataset.id || '0', 10) || 0;
            return idB - idA;
        });

        // Reordenar en el DOM para que el paginado muestre los más nuevos en la página 1
        const tbody = document.getElementById(isAssigned ? 'my-assigned-tbody' : 'my-requested-tbody');
        if (tbody) {
            matchedRows.forEach(row => tbody.appendChild(row));
        }

        const totalMatched = matchedRows.length;
        const pageSize = state.pageSize;
        const totalPages = Math.max(1, Math.ceil(totalMatched / pageSize));

        if (state.page > totalPages) {
            state.page = totalPages;
        }

        const startIndex = (state.page - 1) * pageSize;
        const endIndex = startIndex + pageSize;

        // Mostrar solo los elementos de la página actual
        matchedRows.forEach((r, idx) => {
            if (idx >= startIndex && idx < endIndex) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });

        // Sincronizar select de tamaño de página
        const sizeSelect = document.getElementById(`${prefix}-page-size-select`);
        if (sizeSelect && sizeSelect.value !== String(pageSize)) {
            sizeSelect.value = String(pageSize);
        }

        // Actualizar badge del encabezado de la tabla con el total visible
        const tableBadge = document.getElementById(`${prefix}-table-badge`);
        if (tableBadge) {
            const noun = isAssigned ? 'tickets' : 'solicitudes';
            if (totalMatched !== allRows.length) {
                tableBadge.textContent = `${totalMatched} de ${allRows.length} ${noun}`;
            } else {
                tableBadge.textContent = `${totalMatched} ${noun}`;
            }
        }

        // Actualizar barra de paginación
        const pagBar = document.getElementById(`${prefix}-pagination-bar`);
        const pagStart = document.getElementById(`${prefix}-pag-start`);
        const pagEnd = document.getElementById(`${prefix}-pag-end`);
        const pagTotal = document.getElementById(`${prefix}-pag-total`);
        const pagCurrent = document.getElementById(`${prefix}-pag-current`);
        const pagPages = document.getElementById(`${prefix}-pag-pages`);
        const btnPrev = document.getElementById(`${prefix}-btn-prev`);
        const btnNext = document.getElementById(`${prefix}-btn-next`);
        const pagesContainer = document.getElementById(`${prefix}-pagination-pages-container`);

        if (totalMatched > 0) {
            if (pagBar) pagBar.style.display = 'flex';
            if (pagStart) pagStart.textContent = startIndex + 1;
            if (pagEnd) pagEnd.textContent = Math.min(endIndex, totalMatched);
            if (pagTotal) pagTotal.textContent = totalMatched;
            if (pagCurrent) pagCurrent.textContent = state.page;
            if (pagPages) pagPages.textContent = totalPages;

            if (btnPrev) btnPrev.disabled = (state.page <= 1);
            if (btnNext) btnNext.disabled = (state.page >= totalPages);

            // Generar botones numerados de páginas
            if (pagesContainer) {
                pagesContainer.innerHTML = '';
                const startP = Math.max(1, state.page - 2);
                const endP = Math.min(totalPages, startP + 4);
                for (let p = startP; p <= endP; p++) {
                    const pBtn = document.createElement('button');
                    pBtn.type = 'button';
                    pBtn.className = (p === state.page) ? 'btn-primary' : 'btn-secondary';
                    pBtn.style.padding = '0.35rem 0.65rem';
                    pBtn.style.fontSize = '12px';
                    pBtn.onclick = () => {
                        state.page = p;
                        renderCurrentTablePagination();
                        document.getElementById(isAssigned ? 'my-assigned-view' : 'my-requested-view')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    };
                    pBtn.textContent = p;
                    pagesContainer.appendChild(pBtn);
                }
            }
        } else {
            if (pagBar) pagBar.style.display = 'none';
        }

        // Fila de "no se encontraron resultados" en búsqueda
        let emptySearchRow = document.getElementById(`${tableId}-empty-search`);
        if (totalMatched === 0 && allRows.length > 0) {
            if (!emptySearchRow) {
                emptySearchRow = document.createElement('tr');
                emptySearchRow.id = `${tableId}-empty-search`;
                emptySearchRow.innerHTML = '<td colspan="8" style="text-align:center; padding:2rem; color:var(--fd-text-muted);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:6px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>No se encontraron tickets con los filtros seleccionados.</td>';
                document.querySelector(`#${tableId} tbody`).appendChild(emptySearchRow);
            } else {
                emptySearchRow.style.display = '';
            }
        } else if (emptySearchRow) {
            emptySearchRow.style.display = 'none';
        }
    }

    window.setQuickMyStatusFilter = function(statusSlug) {
        const select = document.getElementById('my-tickets-filter-status');
        if (select) {
            select.value = statusSlug;
            onMyTicketsFilterChange();
        }
    };

    window.resetMyTicketsFilters = function() {
        const s = document.getElementById('my-tickets-search');
        const st = document.getElementById('my-tickets-filter-status');
        const p = document.getElementById('my-tickets-filter-priority');
        const a = document.getElementById('my-tickets-filter-area');
        if (s) s.value = '';
        if (st) st.value = '';
        if (p) p.value = '';
        if (a) a.value = '';
        onMyTicketsFilterChange();
    };

    // Exponer función de renderizado global para integración de navegación
    window.renderCurrentMyTicketsPagination = renderCurrentTablePagination;

    // Inicializar la paginación de entrada
    document.addEventListener('DOMContentLoaded', function() {
        renderCurrentTablePagination();
    });
    // Y ejecutar inmediatamente por si ya cargó el DOM
    renderCurrentTablePagination();
})();
</script>

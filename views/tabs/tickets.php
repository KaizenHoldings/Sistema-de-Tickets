<section id="tickets-tab" class="tab-content <?= ($activeTab === 'tickets-tab') ? 'active' : '' ?>">
                <div class="fd-view-header" style="margin-bottom: 0.85rem;">
                    <div class="fd-view-title">
                        <h1 id="tickets-view-heading" style="display:inline-flex; align-items:center; gap:0.6rem;">
                            <span>Gestión de Tickets</span>
                        </h1>
                        <p>Vista general y seguimiento en tiempo real de solicitudes de soporte</p>
                    </div>

                    <div class="fd-header-actions" style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
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

                        <!-- BOTÓN CON DROPDOWN DE RANGO DE TIEMPO PARA SINCRONIZAR SHAREPOINT -->
                        <div class="fd-sync-dropdown-wrapper" style="position:relative; display:inline-block;">
                            <div style="display:inline-flex; border-radius:6px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                <button type="button" class="btn-secondary" id="btn-sync-main" style="font-size:12px; padding:0.45rem 0.75rem; border-top-right-radius:0; border-bottom-right-radius:0; border-right:none; display:inline-flex; align-items:center; gap:0.35rem;" onclick="syncSharePointByRange('recent', this)" title="Sincronizar los 50 tickets más recientes y novedades de SharePoint">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                                    Sincronizar Recientes
                                </button>
                                <button type="button" class="btn-secondary" id="btn-sync-dropdown-toggle" style="font-size:11px; padding:0.45rem 0.5rem; border-top-left-radius:0; border-bottom-left-radius:0;" onclick="toggleSyncMenu(event)" title="Elegir cantidad o rango de sincronización">
                                    ▼
                                </button>
                            </div>
                            <div class="fd-sync-menu" id="fd-sync-menu" style="display:none; position:absolute; right:0; top:calc(100% + 4px); background:#FFFFFF; border:1px solid #CBD5E1; border-radius:8px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); min-width:230px; z-index:1000; overflow:hidden;">
                                <div style="padding:0.4rem 0.75rem; font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase; background:#F8FAFC; border-bottom:1px solid #E2E8F0;">
                                    Opciones de Sincronización
                                </div>
                                <button type="button" class="fd-sync-menu-item" onclick="syncSharePointByRange('recent', this)">
                                    <strong>Últimos 50 tickets</strong> (Novedades y cambios recientes)
                                </button>
                                <button type="button" class="fd-sync-menu-item" onclick="syncSharePointByRange('100', this)">
                                    <strong>Últimos 100 tickets</strong> (Recomendado)
                                </button>
                                <button type="button" class="fd-sync-menu-item" onclick="syncSharePointByRange('300', this)">
                                    <strong>Últimos 300 tickets</strong> (Rango amplio)
                                </button>
                                <div style="border-top:1px solid #E2E8F0; margin:0.2rem 0;"></div>
                                <button type="button" class="fd-sync-menu-item" onclick="syncSharePointByRange('all', this)">
                                    <strong>Todos los tickets</strong> (Histórico completo)
                                </button>
                                <div style="border-top:1px solid #E2E8F0; margin:0.2rem 0;"></div>
                                <button type="button" class="fd-sync-menu-item" onclick="syncSharePointByRange('24h', this)">
                                    <strong>Últimas 24 horas</strong> (Por fecha de creación/edición)
                                </button>
                                <button type="button" class="fd-sync-menu-item" onclick="syncSharePointByRange('1w', this)">
                                    <strong>Última semana</strong> (Por fecha de creación/edición)
                                </button>
                            </div>
                        </div>


                        <?php if ($role === 'admin'): ?>
                        <button class="btn-secondary" style="font-size:12px; padding:0.45rem 0.8rem; display:inline-flex; align-items:center; gap:0.35rem; color:#DC2626; border-color:#FECACA;" onclick="clearAllTickets(this)" title="Borrar todos los tickets de la base de datos">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Vaciar Tickets
                        </button>
                        <?php endif; ?>

                        <button class="btn-secondary" style="font-size:12px; padding:0.45rem 0.8rem; display:inline-flex; align-items:center; gap:0.35rem;" onclick="switchNavTab('my-requests-tab')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Mis Solicitudes (<?= count($myRequests) ?>)
                        </button>

                        <button class="btn-primary" onclick="openCreateTicketModal()">
                            + Crear Solicitud
                        </button>
                    </div>
                </div>

                <!-- BARRA AVANZADA DE FILTROS & VISTAS RÁPIDAS -->
                <div class="fd-tickets-filter-wrapper">
                    <!-- 1. Vistas Rápidas (Tabs) + Contador y Botón Limpiar -->
                    <div class="fd-quick-tabs-container">
                        <div class="fd-quick-tabs" id="fd-quick-tabs-bar">
                            <button type="button" class="fd-quick-tab active" data-tab="all" onclick="applyQuickTab('all')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                Todos
                            </button>
                            <button type="button" class="fd-quick-tab" data-tab="active" onclick="applyQuickTab('active')">
                                <span class="fd-quick-tab-dot" style="background:#2563EB;"></span>
                                Tickets Activos
                            </button>
                            <button type="button" class="fd-quick-tab" data-tab="my" onclick="applyQuickTab('my')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Mis Tickets
                            </button>
                            <button type="button" class="fd-quick-tab" data-tab="unassigned" onclick="applyQuickTab('unassigned')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                                Sin Asignar
                            </button>
                            <button type="button" class="fd-quick-tab" data-tab="critical" onclick="applyQuickTab('critical')">
                                <span class="fd-quick-tab-dot" style="background:#DC2626;"></span>
                                Críticos / Urgentes
                            </button>
                        </div>

                        <div class="fd-filter-stats-actions">
                            <span class="fd-filter-counter" id="tickets-filter-summary">
                                Mostrando <strong id="tickets-filtered-count"><?= count($allTickets) ?></strong> de <span id="tickets-total-count"><?= count($allTickets) ?></span> tickets
                            </span>
                            <button type="button" id="btn-clear-filters" class="btn-clear-filters" onclick="resetAllTicketFilters()" title="Restablecer todos los filtros a la vista general">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                    <path d="M3 3v5h5"/>
                                </svg>
                                Limpiar filtros
                            </button>
                        </div>
                    </div>

                    <!-- 2. Controles de Filtrado en Barra Horizontal (Campos SharePoint) -->
                    <div class="fd-filters-toolbar">
                        <!-- Fila 1: Buscador Global (Solicitud/Código/Obs) + Empresa + Rango de Fechas -->
                        <div class="fd-filters-row main-row">
                            <!-- 1. Buscador Global -->
                            <div class="fd-filter-group search-group">
                                <label class="fd-filter-label" for="filter-global-search">Buscar (Solicitud, Código u Obs)</label>
                                <div class="fd-input-icon-wrapper">
                                    <svg class="fd-field-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                    <input type="text" id="filter-global-search" class="fd-filter-input" placeholder="Ej: Biométrico, #IT-SP-11370, impresora..." oninput="onTicketFilterInput(this)">
                                    <button type="button" class="fd-input-clear-btn" id="btn-clear-search" onclick="clearFilterField('filter-global-search')">&times;</button>
                                </div>
                            </div>

                            <!-- 2. Empresa con Autocompletado -->
                            <div class="fd-filter-group requester-group">
                                <label class="fd-filter-label" for="filter-company-input">Empresa</label>
                                <div class="fd-input-icon-wrapper">
                                    <svg class="fd-field-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21h18M3 7v14M21 7v14M6 11h4M6 15h4M14 11h4M14 15h4M9 21V3h6v18"/>
                                    </svg>
                                    <input type="text" id="filter-company-input" list="companies-datalist" class="fd-filter-input" placeholder="Todas las empresas o escribe..." oninput="onTicketFilterInput(this)" autocomplete="off">
                                    <datalist id="companies-datalist">
                                        <option value="">Todas las Empresas</option>
                                        <?php foreach ($uniqueCompanies as $comp): ?>
                                            <option value="<?= htmlspecialchars($comp) ?>"><?= htmlspecialchars($comp) ?></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <button type="button" class="fd-input-clear-btn" id="btn-clear-company" onclick="clearFilterField('filter-company-input')">&times;</button>
                                </div>
                            </div>

                            <!-- 3. Filtro de Rango de Fechas (Fecha de Solicitud) -->
                            <div class="fd-filter-group date-group">
                                <label class="fd-filter-label">Fecha de Solicitud</label>
                                <div class="fd-date-range-wrapper">
                                    <input type="date" id="filter-date-from" class="fd-date-input" onchange="applyAllFilters()" title="Desde">
                                    <span class="fd-date-sep">a</span>
                                    <input type="date" id="filter-date-to" class="fd-date-input" onchange="applyAllFilters()" title="Hasta">
                                    <div class="fd-date-quick-btns">
                                        <button type="button" class="fd-date-quick-btn" onclick="setDateRangeQuick('today')">Hoy</button>
                                        <button type="button" class="fd-date-quick-btn" onclick="setDateRangeQuick('7days')">7 días</button>
                                        <button type="button" class="fd-date-quick-btn" onclick="setDateRangeQuick('month')">Este mes</button>
                                        <button type="button" class="fd-date-quick-btn" onclick="setDateRangeQuick('clear')">Todo</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fila 2: Estatus + Área + Responsable -->
                        <div class="fd-filters-row dropdowns-row">
                            <!-- Estatus SharePoint -->
                            <div class="fd-filter-control">
                                <label class="fd-filter-label" for="filter-status-input">Estatus</label>
                                <div class="fd-input-icon-wrapper">
                                    <input type="text" id="filter-status-input" list="status-datalist" class="fd-filter-input" placeholder="Todos los estatus..." oninput="onTicketFilterInput(this)" autocomplete="off">
                                    <datalist id="status-datalist">
                                        <option value="">Todos los Estatus</option>
                                        <?php foreach ($uniqueStatuses as $stVal => $stLabel): ?>
                                            <option value="<?= htmlspecialchars($stVal) ?>"><?= htmlspecialchars($stLabel) ?></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <button type="button" class="fd-input-clear-btn" onclick="clearFilterField('filter-status-input')">&times;</button>
                                </div>
                            </div>

                            <!-- Área SharePoint (IT / SSGG) -->
                            <div class="fd-filter-control">
                                <label class="fd-filter-label" for="filter-area-input">Área</label>
                                <div class="fd-input-icon-wrapper">
                                    <input type="text" id="filter-area-input" list="area-datalist" class="fd-filter-input" placeholder="Todas las áreas..." oninput="onTicketFilterInput(this)" autocomplete="off">
                                    <datalist id="area-datalist">
                                        <option value="">Todas las Áreas</option>
                                        <?php foreach ($uniqueAreas as $acKey => $acLabel): ?>
                                            <option value="<?= htmlspecialchars($acKey) ?>"><?= htmlspecialchars($acLabel) ?></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <button type="button" class="fd-input-clear-btn" onclick="clearFilterField('filter-area-input')">&times;</button>
                                </div>
                            </div>

                            <!-- Responsable SharePoint -->
                            <div class="fd-filter-control">
                                <label class="fd-filter-label" for="filter-responsable-input">Responsable</label>
                                <div class="fd-input-icon-wrapper">
                                    <input type="text" id="filter-responsable-input" list="responsable-datalist" class="fd-filter-input" placeholder="Todos los responsables..." oninput="onTicketFilterInput(this)" autocomplete="off">
                                    <datalist id="responsable-datalist">
                                        <option value="">Todos los Responsables</option>
                                        <option value="Sin Asignar">Sin Asignar</option>
                                        <?php foreach ($uniqueResponsables as $resp): ?>
                                            <option value="<?= htmlspecialchars($resp) ?>"><?= htmlspecialchars($resp) ?></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <button type="button" class="fd-input-clear-btn" onclick="clearFilterField('filter-responsable-input')">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUB-VISTA 1: LISTA TABULAR (CAMPOS SHAREPOINT) -->
                <div id="tickets-subview-list" class="fd-table-card">
                    <div class="fd-table-responsive">
                        <table class="fd-table" id="all-tickets-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 240px;">Solicitud</th>
                                    <th style="min-width: 130px;">Empresa</th>
                                    <th style="min-width: 90px;">Área</th>
                                    <th style="min-width: 130px;">Responsable</th>
                                    <th style="min-width: 200px;">Observaciones</th>
                                    <th style="min-width: 120px;">Estatus</th>
                                    <th style="min-width: 135px;">Fecha Solicitud</th>
                                    <th style="min-width: 135px;">Fecha Propuesta</th>
                                    <th style="min-width: 135px;">Fecha Cierre</th>
                                    <th style="text-align: center; min-width: 120px;">Acción</th>
                                </tr>
                            </thead>
                        <tbody id="all-tickets-tbody">
                            <?php if (empty($allTickets)): ?>
                                <tr id="all-tickets-no-data">
                                    <td colspan="10" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">
                                        No hay tickets registrados en esta vista.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allTickets as $t): ?>
                                    <?php
                                        // Resolver valores de SharePoint
                                        $company = !empty($t['company_name']) ? $t['company_name'] : (!empty($t['requester_name']) ? $t['requester_name'] : 'N/A');
                                        $areaCode = strtoupper(trim($t['area_code'] ?? ''));
                                        if (empty($areaCode)) {
                                            $areaCode = ((int)($t['area_id'] ?? 0) === 3) ? 'SSGG' : 'IT';
                                        }
                                        $areaBadgeClass = ($areaCode === 'SSGG') ? 'fd-area-ssgg' : 'fd-area-it';
                                        $areaIcon = ($areaCode === 'SSGG') 
                                             ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>' 
                                             : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';

                                        $responsable = !empty($t['responsable']) ? $t['responsable'] : (!empty($t['agent_name']) ? $t['agent_name'] : '');
                                        $observations = trim($t['observations'] ?? '');

                                        $spStatus = !empty($t['sp_status']) ? $t['sp_status'] : ($t['status_name'] ?? 'Pendiente');
                                        $statusClass = match (mb_strtolower($spStatus)) {
                                            'procesado' => 'fd-status-procesado',
                                            'en proceso', 'en_proceso' => 'fd-status-en-proceso',
                                            'stand by', 'stand_by' => 'fd-status-stand-by',
                                            default => 'fd-status-pendiente'
                                        };
                                    ?>
                                    <tr class="ticket-row" 
                                        data-ticket-id="<?= (int)$t['id'] ?>" 
                                        data-code="<?= htmlspecialchars(strtolower($t['code'] ?? '')) ?>" 
                                        data-title="<?= htmlspecialchars(strtolower($t['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-company="<?= htmlspecialchars(strtolower($company), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-area-id="<?= (int)($t['area_id'] ?? 0) ?>" 
                                        data-area-name="<?= htmlspecialchars(strtolower($areaCode), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-responsable="<?= htmlspecialchars(strtolower($responsable ?: 'sin asignar'), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-observations="<?= htmlspecialchars(strtolower($observations), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-sp-status="<?= htmlspecialchars(strtolower($spStatus), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-priority="<?= htmlspecialchars(strtolower($t['priority'] ?? 'medium')) ?>" 
                                        data-status-id="<?= (int)$t['status_id'] ?>" 
                                        data-status-name="<?= htmlspecialchars(strtolower($spStatus), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-agent-id="<?= (int)($t['assigned_agent_id'] ?? 0) ?>" 
                                        data-agent-name="<?= htmlspecialchars(strtolower($responsable ?: 'sin asignar')) ?>" 
                                        data-requester-id="<?= (int)($t['requester_id'] ?? 0) ?>" 
                                        data-requester-name="<?= htmlspecialchars(strtolower($company), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-requester-email="<?= htmlspecialchars(strtolower($t['requester_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" 
                                        data-date="<?= htmlspecialchars(substr($t['created_at'] ?? '', 0, 10)) ?>"
                                        data-due-date="<?= htmlspecialchars($t['due_date'] ?? '') ?>"
                                        data-resolved-at="<?= htmlspecialchars($t['resolved_at'] ?? '') ?>"
                                        data-created-at="<?= htmlspecialchars($t['created_at'] ?? '') ?>"
                                        data-timestamp="<?= strtotime($t['created_at'] ?? '2000-01-01 00:00:00') ?>">
                                        <!-- 1. Solicitud (Código + Asunto) -->
                                        <td>
                                            <span class="fd-ticket-code"><?= htmlspecialchars($t['code']) ?></span>
                                            <a class="fd-ticket-link" onclick="openTicketModal(<?= $t['id'] ?>)">
                                                <?= htmlspecialchars($t['title']) ?>
                                            </a>
                                        </td>
                                        <!-- 2. Empresa -->
                                        <td>
                                            <div style="font-weight:600; color:#1E293B; font-size:13px;">
                                                <?= htmlspecialchars($company) ?>
                                            </div>
                                        </td>
                                        <!-- 3. Área -->
                                        <td>
                                            <span class="fd-area-badge <?= $areaBadgeClass ?>">
                                                <?= $areaIcon ?><?= htmlspecialchars($areaCode) ?>
                                            </span>
                                        </td>
                                        <!-- 4. Responsable -->
                                        <td>
                                            <span class="agent-name-display" style="font-weight:500; font-size:12px; color:#334155;">
                                                <?= !empty($responsable) ? htmlspecialchars($responsable) : '<em style="color:#94a3b8">Sin Asignar</em>' ?>
                                            </span>
                                        </td>
                                        <!-- 5. Observaciones -->
                                        <td>
                                            <div class="fd-observations-cell" title="<?= htmlspecialchars($observations) ?>">
                                                <?= !empty($observations) ? htmlspecialchars($observations) : '<span style="color:#CBD5E1;">—</span>' ?>
                                            </div>
                                        </td>
                                        <!-- 6. Estatus -->
                                        <td>
                                            <span class="fd-status-badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($spStatus) ?>
                                            </span>
                                        </td>
                                        <!-- 7. Fecha Solicitud -->
                                        <td style="color:var(--fd-text-dim); font-size:12px; white-space:nowrap;">
                                            <?= !empty($t['created_at']) ? htmlspecialchars(substr($t['created_at'], 0, 16)) : '<span style="color:#CBD5E1;">—</span>' ?>
                                        </td>
                                        <!-- 8. Fecha Propuesta -->
                                        <td style="color:var(--fd-text-dim); font-size:12px; white-space:nowrap;">
                                            <?= !empty($t['due_date']) ? htmlspecialchars(substr($t['due_date'], 0, 16)) : '<span style="color:#CBD5E1;">—</span>' ?>
                                        </td>
                                        <!-- 9. Fecha Cierre -->
                                        <td style="color:var(--fd-text-dim); font-size:12px; white-space:nowrap;">
                                            <?= !empty($t['resolved_at']) ? htmlspecialchars(substr($t['resolved_at'], 0, 16)) : '<span style="color:#CBD5E1;">—</span>' ?>
                                        </td>
                                        <!-- 10. Acción -->
                                        <td style="text-align: center;">
                                            <div style="display:inline-flex; gap:0.4rem; align-items:center;">
                                                <button class="btn-secondary" style="padding:0.3rem 0.6rem; font-size:11px;" onclick="event.stopPropagation(); openTicketModal(<?= $t['id'] ?>)">
                                                    Ver Detalle
                                                </button>
                                                <?php if (in_array($role, ['admin', 'manager'])): ?>
                                                    <?php if (empty($t['assigned_agent_id']) && empty($responsable)): ?>
                                                        <button class="btn-primary" style="padding:0.3rem 0.6rem; font-size:11px; background:#10B981;" onclick="event.stopPropagation(); openAssignAgentDialog(<?= $t['id'] ?>, <?= $t['area_id'] ?>)">
                                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Asignar
                                                        </button>
                                                    <?php endif; ?>
                                                <?php elseif ($role === 'colaborador' && empty($t['assigned_agent_id']) && empty($responsable) && (int)$t['area_id'] === $userAreaId): ?>
                                                    <button class="btn-primary" style="padding:0.3rem 0.6rem; font-size:11px; background:#10B981;" onclick="event.stopPropagation(); pickupTicket(<?= $t['id'] ?>)">
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>Tomar
                                                    </button>
                                                <?php endif; ?>
                                                <?php 
                                                    $canDeleteThisTicket = ($role === 'admin') 
                                                        || ($role === 'manager' && (int)$t['area_id'] === $userAreaId)
                                                        || ((int)($t['requester_id'] ?? 0) === (int)$currentUser['id'] && (int)$t['status_id'] === 1);
                                                ?>
                                                <?php if ($canDeleteThisTicket): ?>
                                                    <button class="btn-secondary" style="padding:0.3rem 0.5rem; font-size:11px; color:#DC2626; border-color:#FECACA; background:#FEF2F2;" onclick="event.stopPropagation(); deleteTicket(<?= $t['id'] ?>, '<?= htmlspecialchars($t['code'], ENT_QUOTES) ?>', <?= !empty($t['sharepoint_id']) ? (int)$t['sharepoint_id'] : 0 ?>)" title="Eliminar Ticket">
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
                </div>

                    <!-- BARRA DE PAGINACIÓN FRESHDESK (PARAMETRIZABLE: 10, 20, 50, 100) -->
                    <div class="fd-pagination-bar" id="tickets-pagination-bar" style="display:flex; justify-content:space-between; align-items:center; padding:0.9rem 1.5rem; background:#F8FAFC; border-top:1px solid #E2E8F0; font-size:13px; color:#64748B; flex-wrap:wrap; gap:0.75rem;">
                        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                            <span>Mostrando <strong id="pag-start-item" style="color:#0F172A;">1</strong> a <strong id="pag-end-item" style="color:#0F172A;">20</strong> de <strong id="pag-total-items" style="color:#0F172A;"><?= count($allTickets) ?></strong> tickets</span>
                            <span style="color:#CBD5E1;">|</span>
                            <span style="font-size:12px; color:#64748B;">Página <strong id="pag-current-page" style="color:#0284C7;">1</strong> de <strong id="pag-total-pages">1</strong></span>
                            <span style="color:#CBD5E1;">|</span>
                            <label for="tickets-page-size-select" style="font-size:12px; color:#64748B; display:inline-flex; align-items:center; gap:0.4rem;">
                                <span>Mostrar:</span>
                                <select id="tickets-page-size-select" onchange="onTicketsPageSizeChange(this.value)" style="padding:0.25rem 0.5rem; font-size:12px; font-weight:600; color:#0F172A; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:4px; cursor:pointer;" title="Seleccionar cantidad de tickets por página">
                                    <option value="10">10 por pág.</option>
                                    <option value="20" selected>20 por pág.</option>
                                    <option value="50">50 por pág.</option>
                                    <option value="100">100 por pág.</option>
                                </select>
                            </label>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.4rem;" id="tickets-pagination-controls">
                            <button type="button" class="btn-secondary" id="btn-pag-prev" onclick="changeTicketPage(-1)" style="padding:0.35rem 0.75rem; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Anterior
                            </button>
                            <div id="tickets-pagination-pages" style="display:flex; gap:0.25rem;"></div>
                            <button type="button" class="btn-secondary" id="btn-pag-next" onclick="changeTicketPage(1)" style="padding:0.35rem 0.75rem; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;">
                                Siguiente <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </div>
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
                                            <div class="ticket-card prio-<?= htmlspecialchars($ticket['priority']) ?>" 
                                                 draggable="true" 
                                                 data-ticket-id="<?= $ticket['id'] ?>"
                                                 data-code="<?= htmlspecialchars(strtolower($ticket['code'] ?? '')) ?>" 
                                                 data-title="<?= htmlspecialchars(strtolower($ticket['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" 
                                                 data-priority="<?= htmlspecialchars(strtolower($ticket['priority'] ?? 'medium')) ?>" 
                                                 data-status-id="<?= (int)$ticket['status_id'] ?>" 
                                                 data-area-id="<?= (int)($ticket['area_id'] ?? 0) ?>" 
                                                 data-agent-id="<?= (int)($ticket['assigned_agent_id'] ?? 0) ?>" 
                                                 data-requester-name="<?= htmlspecialchars(strtolower($ticket['requester_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" 
                                                 data-date="<?= htmlspecialchars(substr($ticket['created_at'] ?? '', 0, 10)) ?>">
                                                <div class="ticket-code"><?= htmlspecialchars($ticket['code']) ?></div>
                                                <div class="ticket-title"><?= htmlspecialchars($ticket['title']) ?></div>

                                                <div style="display: flex; gap: 0.4rem; margin-bottom: 0.4rem;">
                                                    <span class="fd-prio-badge fd-prio-<?= htmlspecialchars($ticket['priority']) ?>">
                                                        <?= htmlspecialchars($ticket['priority']) ?>
                                                    </span>
                                                </div>

                                                <div class="ticket-footer">
                                                    <span>
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><?= $ticket['agent_name'] ? htmlspecialchars($ticket['agent_name']) : '<em style="color:#94a3b8">Sin Asignar</em>' ?>
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

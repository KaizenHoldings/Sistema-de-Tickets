<section id="pending-tab" class="tab-content <?= ($activeTab === 'pending-tab') ? 'active' : '' ?>">
                <!-- CABECERA DE LA VISTA -->
                <div class="fd-view-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
                    <div class="fd-view-title">
                        <h1 style="display:flex; align-items:center; gap:0.5rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <span>Tickets Pendientes y Hoja de Tareas</span>
                        </h1>
                        <p>Visualización operativa y directa de lo que requiere atención. Conozca qué se necesita resolver, el solicitante y el responsable a cargo.</p>
                    </div>

                    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                        <!-- SELECTOR DE VISTA: TARJETAS VS TABLA -->
                        <div class="fd-pending-view-toggle">
                            <button type="button" class="fd-pending-view-btn active" id="btn-pending-view-cards" onclick="switchPendingView('cards')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                <span>Tarjetas de Trabajo</span>
                            </button>
                            <button type="button" class="fd-pending-view-btn" id="btn-pending-view-table" onclick="switchPendingView('table')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                <span>Lista Resumida</span>
                            </button>
                        </div>

                        <!-- CREAR TICKET DIRECTO -->
                        <button type="button" class="btn-primary" style="display:inline-flex; align-items:center; gap:6px; padding:0.5rem 1rem; font-size:13px;" onclick="openCreateTicketModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Nueva Solicitud</span>
                        </button>
                    </div>
                </div>

                <!-- KPI CARDS (RESUMEN OPERATIVO) -->
                <div class="fd-pending-kpi-grid">
                    <div class="fd-admin-kpi-card kpi-total">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Total Pendientes</span>
                            <div class="fd-admin-kpi-icon icon-blue">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number" id="kpi-pending-total"><?= $pendingCountTotal ?></div>
                        <div class="fd-admin-kpi-subtext">Solicitudes por resolver</div>
                    </div>

                    <div class="fd-admin-kpi-card kpi-managers" style="border-left-color: #DC2626;">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Sin Asignar (Urgentes)</span>
                            <div class="fd-admin-kpi-icon" style="background:#FEF2F2; color:#DC2626;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number" style="color:#DC2626;" id="kpi-pending-unassigned"><?= $pendingCountUnassigned ?></div>
                        <div class="fd-admin-kpi-subtext">Requieren tomar o asignar técnico</div>
                    </div>

                    <div class="fd-admin-kpi-card kpi-active">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">En Proceso Activo</span>
                            <div class="fd-admin-kpi-icon icon-green">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number" style="color:#059669;" id="kpi-pending-inprogress"><?= $pendingCountInProgress ?></div>
                        <div class="fd-admin-kpi-subtext">Atendidos por el equipo</div>
                    </div>

                    <div class="fd-admin-kpi-card kpi-areas">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Stand By / En Espera</span>
                            <div class="fd-admin-kpi-icon icon-amber">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number" style="color:#D97706;" id="kpi-pending-standby"><?= $pendingCountStandBy ?></div>
                        <div class="fd-admin-kpi-subtext">Pausados por compras/usuario</div>
                    </div>
                </div>

                <!-- TOOLBAR Y FILTROS OPERATIVOS -->
                <div class="fd-pending-toolbar">
                    <!-- Búsqueda en vivo -->
                    <div class="fd-user-search-wrap" style="width: 290px;">
                        <svg class="fd-user-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="pending-search-input" placeholder="Buscar por tarea, asunto, código o persona..." oninput="filterPendingView()">
                    </div>

                    <!-- Píldoras de filtrado rápido -->
                    <div class="fd-pending-filter-group" id="pending-filter-pills">
                        <button type="button" class="fd-pending-pill-btn active" data-filter="all" onclick="setPendingFilter('all')">
                            <span>Todos (<?= $pendingCountTotal ?>)</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="my" onclick="setPendingFilter('my')" style="border-color:#BFDBFE; background:#EFF6FF; color:#1D4ED8;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span>Mis Asignados</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="unassigned" onclick="setPendingFilter('unassigned')" style="border-color:#FDE68A; background:#FFFBEB; color:#B45309;">
                            <span>⚠️ Sin Asignar (<?= $pendingCountUnassigned ?>)</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="in_progress" onclick="setPendingFilter('in_progress')">
                            <span>▶ En Proceso (<?= $pendingCountInProgress ?>)</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="stand_by" onclick="setPendingFilter('stand_by')">
                            <span>⏸ Stand By (<?= $pendingCountStandBy ?>)</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="urgent" onclick="setPendingFilter('urgent')" style="color:#DC2626;">
                            <span>🔥 Urgentes / Alta (<?= $pendingCountUrgent ?>)</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="it" onclick="setPendingFilter('it')">
                            <span>💻 IT</span>
                        </button>
                        <button type="button" class="fd-pending-pill-btn" data-filter="ssgg" onclick="setPendingFilter('ssgg')">
                            <span>🏢 SSGG</span>
                        </button>
                    </div>
                </div>

                <!-- CONTENEDOR 1: TARJETAS DE ACCIÓN OPERATIVA (¿QUÉ HAY QUE HACER?) -->
                <div id="pending-cards-container" class="fd-task-cards-grid">
                    <?php if (empty($pendingTickets)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3.5rem 1rem; background: #FFFFFF; border: 1px dashed #CBD5E1; border-radius: 12px;">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" style="margin-bottom:0.75rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <h3 style="font-size: 16px; color: #0F172A; margin-bottom: 0.25rem;">¡Excelente! No hay tickets pendientes en este momento</h3>
                            <p style="font-size: 13px; color: #64748B;">Todas las solicitudes han sido resueltas y procesadas satisfactoriamente.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingTickets as $pt): ?>
                            <?php 
                                $ptPrio = htmlspecialchars($pt['priority'] ?? 'medium');
                                $ptArea = strtoupper($pt['area_code'] ?? (((int)($pt['area_id'] ?? 0) === 3) ? 'SSGG' : 'IT'));
                                $ptIsUnassigned = (empty($pt['assigned_agent_id']) || empty($pt['responsable']) || $pt['responsable'] === 'Sin Asignar');
                                $ptStatusId = (int)($pt['status_id'] ?? 1);
                                $ptSpStatus = mb_strtolower(trim($pt['sp_status'] ?? ''));
                                $ptStatusLabel = !empty($pt['sp_status']) ? htmlspecialchars($pt['sp_status']) : ($pt['status_name'] ?? 'Pendiente');
                                $ptDescription = !empty($pt['description']) ? trim($pt['description']) : (!empty($pt['observations']) ? trim($pt['observations']) : '');
                                $isMyAssigned = ((int)($pt['assigned_agent_id'] ?? 0) === (int)$userId);
                            ?>
                            <div class="fd-task-card prio-<?= $ptPrio ?>" 
                                 onclick="openTicketModal(<?= (int)$pt['id'] ?>)"
                                 data-ticket-id="<?= (int)$pt['id'] ?>"
                                 data-code="<?= strtolower($pt['code']) ?>"
                                 data-title="<?= htmlspecialchars(strtolower($pt['title'])) ?>"
                                 data-desc="<?= htmlspecialchars(strtolower($ptDescription)) ?>"
                                 data-requester="<?= htmlspecialchars(strtolower($pt['requester_name'] ?? '')) ?>"
                                 data-company="<?= htmlspecialchars(strtolower($pt['company_name'] ?? '')) ?>"
                                 data-agent="<?= htmlspecialchars(strtolower($pt['responsable'] ?? $pt['agent_name'] ?? 'sin asignar')) ?>"
                                 data-agent-id="<?= (int)($pt['assigned_agent_id'] ?? 0) ?>"
                                 data-area="<?= $ptArea ?>"
                                 data-status-id="<?= $ptStatusId ?>"
                                 data-sp-status="<?= $ptSpStatus ?>"
                                 data-priority="<?= $ptPrio ?>"
                                 data-is-my="<?= $isMyAssigned ? '1' : '0' ?>"
                                 data-is-unassigned="<?= $ptIsUnassigned ? '1' : '0' ?>">
                                
                                <div>
                                    <!-- HEADER: CÓDIGO & BADGES -->
                                    <div class="fd-task-card-header">
                                        <span class="fd-task-card-code"><?= htmlspecialchars($pt['code']) ?></span>
                                        <div class="fd-task-badges-row">
                                            <!-- Badge Área -->
                                            <span style="font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px; <?= ($ptArea === 'IT') ? 'background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE;' : 'background:#F3E8FF; color:#7E22CE; border:1px solid #E9D5FF;' ?>">
                                                <?= $ptArea ?>
                                            </span>

                                            <!-- Badge Estatus -->
                                            <span class="fd-status-badge fd-status-<?= htmlspecialchars($pt['status_slug'] ?? 'unassigned') ?>">
                                                <?= $ptStatusLabel ?>
                                            </span>

                                            <!-- Badge Prioridad -->
                                            <span class="fd-prio-badge fd-prio-<?= $ptPrio ?>">
                                                <?= $ptPrio ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- TÍTULO DEL TICKET -->
                                    <div class="fd-task-title"><?= htmlspecialchars($pt['title']) ?></div>

                                    <!-- CAJA DESTACADA: ¿QUÉ HAY QUE HACER? -->
                                    <div class="fd-task-action-box">
                                        <div class="fd-task-action-label">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14l2 2 4-4"/></svg>
                                            <span>¿Qué hay que resolver?</span>
                                        </div>
                                        <div class="fd-task-action-text">
                                            <?= !empty($ptDescription) ? nl2br(htmlspecialchars($ptDescription)) : '<em>Sin detalle adicional provisto en la solicitud inicial. Atender de acuerdo al asunto.</em>' ?>
                                        </div>
                                    </div>

                                    <!-- METADATOS: SOLICITANTE & FECHA -->
                                    <div class="fd-task-meta-row">
                                        <div class="fd-task-requester" title="Solicitante">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            <span><strong><?= htmlspecialchars($pt['requester_name'] ?? 'Usuario') ?></strong> <?= !empty($pt['company_name']) ? '· ' . htmlspecialchars($pt['company_name']) : '' ?></span>
                                        </div>
                                        <div class="fd-task-time" title="Fecha de Registro">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <span><?= !empty($pt['created_at']) ? substr($pt['created_at'], 0, 10) : '' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- FOOTER: RESPONSABLE & ACCIONES -->
                                <div class="fd-task-card-footer" onclick="event.stopPropagation()">
                                    <!-- Responsable -->
                                    <div class="fd-task-agent-info">
                                        <?php if ($ptIsUnassigned): ?>
                                            <span class="fd-task-unassigned-tag">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                Sin Asignar
                                            </span>
                                        <?php else: ?>
                                            <div class="fd-avatar-sm" style="width:26px; height:26px; font-size:10px; background:#2563EB;">
                                                <?= strtoupper(substr($pt['responsable'] ?? $pt['agent_name'] ?? 'AG', 0, 2)) ?>
                                            </div>
                                            <span style="font-weight:600; color:#1E293B; font-size:12px;"><?= htmlspecialchars($pt['responsable'] ?? $pt['agent_name']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Botones de Acción -->
                                    <div style="display:inline-flex; align-items:center; gap:0.4rem;">
                                        <?php if ($ptIsUnassigned): ?>
                                            <button type="button" class="btn-primary" style="padding:0.35rem 0.7rem; font-size:11.5px; background:#10B981;" onclick="pickupTicket(<?= (int)$pt['id'] ?>)">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                                Tomar
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:11.5px; font-weight:600;" onclick="openTicketModal(<?= (int)$pt['id'] ?>)">
                                            Gestionar &rarr;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- CONTENEDOR 2: TABLA DE TAREAS PENDIENTES (LISTA COMPACTA) -->
                <div id="pending-table-container" class="fd-table-card" style="display:none; margin-bottom:2rem;">
                    <table class="fd-table" id="pending-tasks-table">
                        <thead>
                            <tr>
                                <th>Código & Área</th>
                                <th>¿Qué hay que hacer? (Solicitud)</th>
                                <th>Solicitante</th>
                                <th>Responsable</th>
                                <th>Estatus</th>
                                <th>Prioridad</th>
                                <th>Fecha</th>
                                <th style="text-align:right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingTickets)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:2.5rem; color:#64748B;">No hay tickets pendientes actualmente.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingTickets as $pt): ?>
                                    <?php 
                                        $ptPrio = htmlspecialchars($pt['priority'] ?? 'medium');
                                        $ptArea = strtoupper($pt['area_code'] ?? (((int)($pt['area_id'] ?? 0) === 3) ? 'SSGG' : 'IT'));
                                        $ptIsUnassigned = (empty($pt['assigned_agent_id']) || empty($pt['responsable']) || $pt['responsable'] === 'Sin Asignar');
                                        $ptStatusId = (int)($pt['status_id'] ?? 1);
                                        $ptSpStatus = mb_strtolower(trim($pt['sp_status'] ?? ''));
                                        $ptStatusLabel = !empty($pt['sp_status']) ? htmlspecialchars($pt['sp_status']) : ($pt['status_name'] ?? 'Pendiente');
                                        $ptDescription = !empty($pt['description']) ? trim($pt['description']) : (!empty($pt['observations']) ? trim($pt['observations']) : '');
                                        $isMyAssigned = ((int)($pt['assigned_agent_id'] ?? 0) === (int)$userId);
                                    ?>
                                    <tr class="pending-table-row"
                                        onclick="openTicketModal(<?= (int)$pt['id'] ?>)"
                                        style="cursor:pointer;"
                                        data-ticket-id="<?= (int)$pt['id'] ?>"
                                        data-code="<?= strtolower($pt['code']) ?>"
                                        data-title="<?= htmlspecialchars(strtolower($pt['title'])) ?>"
                                        data-desc="<?= htmlspecialchars(strtolower($ptDescription)) ?>"
                                        data-requester="<?= htmlspecialchars(strtolower($pt['requester_name'] ?? '')) ?>"
                                        data-company="<?= htmlspecialchars(strtolower($pt['company_name'] ?? '')) ?>"
                                        data-agent="<?= htmlspecialchars(strtolower($pt['responsable'] ?? $pt['agent_name'] ?? 'sin asignar')) ?>"
                                        data-agent-id="<?= (int)($pt['assigned_agent_id'] ?? 0) ?>"
                                        data-area="<?= $ptArea ?>"
                                        data-status-id="<?= $ptStatusId ?>"
                                        data-sp-status="<?= $ptSpStatus ?>"
                                        data-priority="<?= $ptPrio ?>"
                                        data-is-my="<?= $isMyAssigned ? '1' : '0' ?>"
                                        data-is-unassigned="<?= $ptIsUnassigned ? '1' : '0' ?>">
                                        <td>
                                            <div style="display:flex; flex-direction:column; gap:3px;">
                                                <strong style="color:#2563EB;"><?= htmlspecialchars($pt['code']) ?></strong>
                                                <span style="font-size:10px; font-weight:700; width:fit-content; padding:1px 5px; border-radius:3px; <?= ($ptArea === 'IT') ? 'background:#EFF6FF; color:#1D4ED8;' : 'background:#F3E8FF; color:#7E22CE;' ?>">
                                                    <?= $ptArea ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td style="max-width: 380px;">
                                            <div style="font-weight:600; color:#0F172A; font-size:13px; margin-bottom:3px;"><?= htmlspecialchars($pt['title']) ?></div>
                                            <div style="font-size:12px; color:#64748B; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                <?= !empty($ptDescription) ? htmlspecialchars($ptDescription) : '<em>Sin observaciones adicionales</em>' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size:12.5px; font-weight:600; color:#1E293B;"><?= htmlspecialchars($pt['requester_name'] ?? 'Usuario') ?></div>
                                            <div style="font-size:11px; color:#94A3B8;"><?= htmlspecialchars($pt['company_name'] ?? 'Kaizen') ?></div>
                                        </td>
                                        <td>
                                            <?php if ($ptIsUnassigned): ?>
                                                <span class="fd-task-unassigned-tag">Sin Asignar</span>
                                            <?php else: ?>
                                                <span style="font-size:12.5px; font-weight:600; color:#2563EB;"><?= htmlspecialchars($pt['responsable'] ?? $pt['agent_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fd-status-badge fd-status-<?= htmlspecialchars($pt['status_slug'] ?? 'unassigned') ?>">
                                                <?= $ptStatusLabel ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fd-prio-badge fd-prio-<?= $ptPrio ?>">
                                                <?= $ptPrio ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px; color:#64748B;">
                                            <?= !empty($pt['created_at']) ? substr($pt['created_at'], 0, 10) : '' ?>
                                        </td>
                                        <td style="text-align:right;" onclick="event.stopPropagation()">
                                            <div style="display:inline-flex; align-items:center; gap:0.35rem;">
                                                <?php if ($ptIsUnassigned): ?>
                                                    <button type="button" class="btn-primary" style="padding:0.3rem 0.6rem; font-size:11px; background:#10B981;" onclick="pickupTicket(<?= (int)$pt['id'] ?>)">
                                                        Tomar
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn-secondary" style="padding:0.3rem 0.65rem; font-size:11px;" onclick="openTicketModal(<?= (int)$pt['id'] ?>)">
                                                    Gestionar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

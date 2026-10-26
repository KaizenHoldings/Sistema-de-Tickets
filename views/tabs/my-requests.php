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
                                        <div style="margin-bottom:0.75rem; color:#94A3B8;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
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
                                            <span class="fd-portal-badge" style="font-size:11px;"><?= htmlspecialchars($mr['area_name'] ?? 'General') ?></span>
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
                                                <?= $mr['agent_name'] ? htmlspecialchars($mr['agent_name']) : '<em style="color:#94a3b8">En espera de asignación</em>' ?>
                                            </span>
                                        </td>
                                        <td style="color:var(--fd-text-dim); font-size:12px;">
                                            <?= htmlspecialchars(substr($mr['created_at'] ?? '', 0, 16)) ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$mr['status_id'] === 4): ?>
                                                <?php if (!empty($mr['csat_rating'])): ?>
                                                    <span style="display:inline-flex; align-items:center; gap:0.3rem; background:#DCFCE7; color:#166534; padding:0.25rem 0.6rem; border-radius:12px; font-size:11px; font-weight:600;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#166534" stroke="#166534" stroke-width="1" style="vertical-align:-1px; margin-right:3px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><?= (int)$mr['csat_rating'] ?>/5 Calificado
                                                    </span>
                                                <?php else: ?>
                                                    <button class="btn-primary" style="background:#D97706; padding:0.25rem 0.55rem; font-size:11px;" onclick="event.stopPropagation(); openCsatModal(<?= $mr['id'] ?>, '<?= htmlspecialchars($mr['code']) ?>')">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="1" style="vertical-align:-1px; margin-right:3px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Calificar Atención
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--fd-text-dim); font-size:12px;">En atención...</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:inline-flex; gap:0.4rem; align-items:center;">
                                                <button class="btn-secondary" style="padding:0.3rem 0.6rem; font-size:11px;" onclick="event.stopPropagation(); openTicketModal(<?= $mr['id'] ?>)">
                                                    Ver Detalle
                                                </button>
                                                <?php if ($role === 'admin' || (int)$mr['status_id'] === 1): ?>
                                                    <button class="btn-secondary" style="padding:0.3rem 0.5rem; font-size:11px; color:#DC2626; border-color:#FECACA; background:#FEF2F2;" onclick="event.stopPropagation(); deleteTicket(<?= $mr['id'] ?>, '<?= htmlspecialchars($mr['code'], ENT_QUOTES) ?>', <?= !empty($mr['sharepoint_id']) ? (int)$mr['sharepoint_id'] : 0 ?>)" title="Eliminar Solicitud">
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
            </section>

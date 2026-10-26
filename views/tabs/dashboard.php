<section id="dashboard-tab" class="tab-content <?= ($activeTab === 'dashboard-tab') ? 'active' : '' ?>">
                <div class="fd-view-header">
                    <div class="fd-view-title">
                        <h1>Panel de Control</h1>
                        <p>Resumen operacional del servicio de asistencia técnica : Kaizen</p>
                    </div>
                    <div class="fd-header-actions" style="display:flex; gap:0.5rem; align-items:center;">
                        <button class="btn-secondary" onclick="syncSharePointTickets(this)" style="display:inline-flex; align-items:center; gap:0.35rem;" title="Sincronizar solicitudes desde SharePoint SSGG e IT">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>Sincronizar SharePoint
                        </button>
                        <a href="api.php?action=export_tickets_csv" class="btn-secondary" style="text-decoration:none; display:inline-flex;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Exportar Reporte a Excel (CSV)
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
                                <span style="color:#DC2626; display:inline-flex; align-items:center; gap:6px;"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#DC2626;"></span>Urgente</span>
                                <span><?= $prios['urgent'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['urgent'] ?? 0) / $totUnres) * 100)) ?>%; background:#EF4444;"></div>
                            </div>
                        </div>

                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#EA580C; display:inline-flex; align-items:center; gap:6px;"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#EA580C;"></span>Alta</span>
                                <span><?= $prios['high'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['high'] ?? 0) / $totUnres) * 100)) ?>%; background:#F97316;"></div>
                            </div>
                        </div>

                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#D97706; display:inline-flex; align-items:center; gap:6px;"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#D97706;"></span>Media</span>
                                <span><?= $prios['medium'] ?? 0 ?></span>
                            </div>
                            <div class="fd-prio-bar-track">
                                <div class="fd-prio-bar-fill" style="width: <?= min(100, round((($prios['medium'] ?? 0) / $totUnres) * 100)) ?>%; background:#F59E0B;"></div>
                            </div>
                        </div>

                        <div class="fd-prio-bar-item">
                            <div class="fd-prio-bar-labels">
                                <span style="color:#059669; display:inline-flex; align-items:center; gap:6px;"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#059669;"></span>Baja</span>
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
                                <div class="fd-sla-stat"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981; margin-right:6px;"></span>Tickets en plazo: <strong><?= max(0, ($kpis['total_tickets'] ?? 0) - ($kpis['overdue_tickets'] ?? 0)) ?></strong></div>
                                <div class="fd-sla-stat"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#EF4444; margin-right:6px;"></span>Excedidos: <strong style="color:#DC2626;"><?= $kpis['overdue_tickets'] ?? 0 ?></strong></div>
                                <div class="fd-sla-stat"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#3B82F6; margin-right:6px;"></span>Meta corporativa: <strong>95.0%</strong></div>
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
                                        <span style="display:flex; align-items:center; gap:4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><?= htmlspecialchars($ag['agent_name']) ?></span>
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

                    <div class="fd-table-responsive">
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
                                        <tr class="ticket-row" data-ticket-id="<?= (int)$t['id'] ?>">
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
                </div>
            </section>

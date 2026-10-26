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
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-muted);">No hay tickets pendientes por asignar en esta área actualmente.</td></tr>
                            <?php else: ?>
                                <?php foreach ($unassignedTickets as $t): ?>
                                    <tr class="ticket-row" data-ticket-id="<?= (int)$t['id'] ?>">
                                        <td>
                                            <strong style="cursor:pointer; color:var(--fd-action-btn);" onclick="openTicketModal(<?= (int)$t['id'] ?>)"><?= htmlspecialchars($t['code']) ?></strong>
                                        </td>
                                        <td>
                                            <a style="cursor:pointer; color:var(--fd-text); text-decoration:none; font-weight:500;" onclick="openTicketModal(<?= (int)$t['id'] ?>)">
                                                <?= htmlspecialchars($t['title']) ?>
                                            </a>
                                            <?= !empty($t['attachment_path']) ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-left:4px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>' : '' ?>
                                            <div style="font-size:11px; color:var(--fd-text-dim);"><?= htmlspecialchars($t['area_name'] ?? 'General') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($t['requester_name']) ?></td>
                                        <td><span class="fd-prio-badge fd-prio-<?= htmlspecialchars($t['priority']) ?>"><?= htmlspecialchars($t['priority']) ?></span></td>
                                        <td style="font-size:12px; color:var(--fd-text-dim);"><?= !empty($t['created_at']) ? substr($t['created_at'], 0, 16) : '' ?></td>
                                        <td>
                                            <?php if ($role === 'colaborador'): ?>
                                                <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981;" onclick="event.stopPropagation(); pickupTicket(<?= (int)$t['id'] ?>)">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>Tomar Ticket
                                                </button>
                                                <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="event.stopPropagation(); openTicketModal(<?= (int)$t['id'] ?>)">
                                                    Ver Detalle
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981;" onclick="event.stopPropagation(); openAssignAgentDialog(<?= (int)$t['id'] ?>, <?= (int)($t['area_id'] ?? 0) ?>)">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Asignar Agente
                                                </button>
                                                <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="event.stopPropagation(); pickupTicket(<?= (int)$t['id'] ?>)">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>Tomar Ticket
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

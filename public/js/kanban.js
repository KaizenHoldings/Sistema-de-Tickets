/**
 * LÓGICA ENTERPRISE SERVICE DESK: KANBAN, HISTORIAL, TRANSICIONES CON NOTA OBLIGATORIA, CSAT Y ASIGNACIÓN DINÁMICA
 */

let currentTicketData = null;
let pendingStatusChange = null;
let isStatusChangeInternal = false;
let selectedCsatRating = 5;

const STATUS_METADATA = {
    1: { name: 'Por Asignar', slug: 'unassigned' },
    2: { name: 'En Progreso', slug: 'in_progress' },
    3: { name: 'Pendiente Usuario', slug: 'pending_user' },
    4: { name: 'Resuelto', slug: 'resolved' }
};

const CSAT_LABELS = {
    1: 'Muy Insatisfecho',
    2: 'Insatisfecho',
    3: 'Neutral',
    4: 'Satisfecho',
    5: 'Muy Satisfecho'
};

const CSAT_EMOJIS = {
    1: '😢',
    2: '🙁',
    3: '😐',
    4: '😊',
    5: '🤩'
};

document.addEventListener('DOMContentLoaded', () => {
    initNavigationTabs();
    initKanbanDragAndDrop();
    initNewTicketForm();
    initSuperAdminForms();
    initAssignModal();
    initAssignFormSubmit();
    initStatusChangeForm();
    initCsatFormSubmit();
    initNotifications();
    initGlobalSearch();
});

function getCsrfToken() {
    return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function initNavigationTabs() {
    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            tab.classList.add('active');
            const targetId = tab.dataset.target;
            const targetContent = document.getElementById(targetId);
            if (targetContent) targetContent.classList.add('active');

            if (targetId === 'tickets-tab') {
                const heading = document.getElementById('tickets-view-heading');
                if (heading) heading.textContent = 'Todos los tickets';
                document.querySelectorAll('#all-tickets-tbody tr.ticket-row').forEach(r => r.style.display = '');
                const emptyRow = document.getElementById('all-tickets-empty-search');
                if (emptyRow) emptyRow.style.display = 'none';
            }

            if (targetId === 'inbox-tab') {
                loadUnassignedTickets();
            }
        });
    });
}

function initKanbanDragAndDrop() {
    const cards = document.querySelectorAll('.ticket-card');
    const columns = document.querySelectorAll('.tickets-container');
    let draggedCard = null;
    let isDragging = false;

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            isDragging = true;
            draggedCard = card;
            card.classList.add('dragging');
            e.dataTransfer.setData('text/plain', card.dataset.ticketId);
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            draggedCard = null;
            columns.forEach(col => col.classList.remove('drag-over'));
            setTimeout(() => { isDragging = false; }, 100);
        });

        card.addEventListener('click', (e) => {
            if (!isDragging) {
                openTicketModal(card.dataset.ticketId);
            }
        });
    });

    columns.forEach(column => {
        column.addEventListener('dragover', (e) => {
            e.preventDefault();
            column.classList.add('drag-over');
        });

        column.addEventListener('dragleave', () => {
            column.classList.remove('drag-over');
        });

        column.addEventListener('drop', (e) => {
            e.preventDefault();
            column.classList.remove('drag-over');
            if (!draggedCard) return;

            const ticketId = parseInt(draggedCard.dataset.ticketId);
            const newStatusId = parseInt(column.dataset.statusId);
            const currentColumn = draggedCard.parentElement;
            const oldStatusId = parseInt(currentColumn.dataset.statusId);

            if (oldStatusId === newStatusId) return;

            const codeEl = draggedCard.querySelector('.ticket-code, .fd-ticket-code, .ticket-id');
            const ticketCode = codeEl ? codeEl.textContent.trim() : `Ticket #${ticketId}`;

            // Solicitar obligatoriamente nota o comentario para aplicar la transición
            requestStatusChange({
                ticketId: ticketId,
                newStatusId: newStatusId,
                oldStatusId: oldStatusId,
                ticketCode: ticketCode,
                draggedCard: draggedCard,
                originColumn: currentColumn,
                targetColumn: column,
                source: 'kanban'
            });
        });
    });
}

// Interceptar cambio de estado desde el dropdown de propiedades en el modal de detalle
function handleDetailStatusChange(selectEl) {
    if (!currentTicketData) return;
    const newStatusId = parseInt(selectEl.value);
    const oldStatusId = parseInt(currentTicketData.status_id);

    if (newStatusId === oldStatusId) return;

    requestStatusChange({
        ticketId: currentTicketData.id,
        newStatusId: newStatusId,
        oldStatusId: oldStatusId,
        ticketCode: currentTicketData.code,
        selectEl: selectEl,
        source: 'modal'
    });
}

// Abrir el modal de cambio de estado solicitando nota/comentario obligatorio
function requestStatusChange(data) {
    pendingStatusChange = data;
    const modal = document.getElementById('status-change-modal');
    if (!modal) return;

    document.getElementById('status-change-ticket-id').value = data.ticketId;
    document.getElementById('status-change-new-status-id').value = data.newStatusId;
    document.getElementById('status-change-code').textContent = data.ticketCode || `Ticket #${data.ticketId}`;

    const fromMeta = STATUS_METADATA[data.oldStatusId] || { name: 'Estado ' + data.oldStatusId, slug: 'unassigned' };
    const toMeta = STATUS_METADATA[data.newStatusId] || { name: 'Estado ' + data.newStatusId, slug: 'resolved' };

    const fromBadge = document.getElementById('status-change-from-badge');
    const toBadge = document.getElementById('status-change-to-badge');

    if (fromBadge) {
        fromBadge.textContent = fromMeta.name;
        fromBadge.className = `fd-status-badge fd-status-${fromMeta.slug}`;
    }
    if (toBadge) {
        toBadge.textContent = toMeta.name;
        toBadge.className = `fd-status-badge fd-status-${toMeta.slug}`;
    }

    const commentInput = document.getElementById('status-change-comment');
    if (commentInput) commentInput.value = '';
    setStatusNoteType(false);

    modal.style.display = 'flex';
}

function cancelStatusChange() {
    if (pendingStatusChange) {
        if (pendingStatusChange.source === 'modal' && pendingStatusChange.selectEl) {
            pendingStatusChange.selectEl.value = pendingStatusChange.oldStatusId;
        }
        pendingStatusChange = null;
    }
    const modal = document.getElementById('status-change-modal');
    if (modal) modal.style.display = 'none';
}

function setStatusNoteType(isInternal) {
    isStatusChangeInternal = isInternal;
    const btnPub = document.getElementById('status-toggle-public');
    const btnPriv = document.getElementById('status-toggle-private');
    if (btnPub) btnPub.classList.toggle('active', !isInternal);
    if (btnPriv) {
        btnPriv.classList.toggle('active', isInternal);
        btnPriv.classList.toggle('note-active', isInternal);
    }
}

function initStatusChangeForm() {
    const form = document.getElementById('status-change-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!pendingStatusChange) return;

        const commentText = document.getElementById('status-change-comment').value.trim();
        if (!commentText) {
            showToast('Debe ingresar un comentario o nota interna explicando el cambio de estado.', 'error');
            return;
        }

        const ticketId = pendingStatusChange.ticketId;
        const newStatusId = pendingStatusChange.newStatusId;
        const isInternal = isStatusChangeInternal;

        try {
            const response = await fetch('api.php?action=update_kanban_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({
                    ticket_id: parseInt(ticketId),
                    status_id: parseInt(newStatusId),
                    comment: commentText,
                    is_internal: isInternal,
                    csrf_token: getCsrfToken()
                })
            });

            const result = await response.json();
            if (result.success) {
                showToast(result.message, 'success');
                const modal = document.getElementById('status-change-modal');
                if (modal) modal.style.display = 'none';

                const toMeta = STATUS_METADATA[newStatusId] || { name: 'Estado ' + newStatusId, slug: 'resolved' };

                // 1. Reflejar en Tablero Kanban
                if (pendingStatusChange.source === 'kanban') {
                    pendingStatusChange.targetColumn.appendChild(pendingStatusChange.draggedCard);
                    updateColumnCounters();
                } else {
                    const card = document.querySelector(`.ticket-card[data-ticket-id="${ticketId}"]`);
                    const targetContainer = document.querySelector(`.tickets-container[data-status-id="${newStatusId}"]`);
                    if (card && targetContainer) {
                        targetContainer.appendChild(card);
                        updateColumnCounters();
                    }
                }

                // 2. Reflejar en Lista Tabular de Tickets y Recientes
                document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(row => {
                    row.dataset.statusId = newStatusId;
                    const badge = row.querySelector('.fd-status-badge');
                    if (badge) {
                        badge.textContent = toMeta.name;
                        badge.className = `fd-status-badge fd-status-${toMeta.slug}`;
                    }
                });

                // 3. Reflejar en Modal de Detalle
                if (currentTicketData && parseInt(currentTicketData.id) === parseInt(ticketId)) {
                    currentTicketData.status_id = newStatusId;
                    currentTicketData.status_name = toMeta.name;
                    currentTicketData.status_slug = toMeta.slug;

                    const statusPill = document.getElementById('modal-ticket-status-pill');
                    if (statusPill) {
                        statusPill.textContent = toMeta.name;
                        statusPill.className = `fd-status-badge fd-status-${toMeta.slug}`;
                    }

                    const statusSelect = document.getElementById('modal-prop-status-select');
                    if (statusSelect) statusSelect.value = newStatusId;

                    loadTicketComments(ticketId);
                }

                const transitionToResolved = (parseInt(newStatusId) === 4);
                pendingStatusChange = null;

                // 4. Si el ticket fue resuelto/cerrado, solicitar Encuesta de Satisfacción (CSAT) solo al solicitante o admin
                const isRequesterOrAdmin = (currentTicketData && parseInt(currentTicketData.requester_id) === parseInt(window.CURRENT_USER_ID)) || (window.CURRENT_USER_ROLE === 'admin');
                if (transitionToResolved && isRequesterOrAdmin) {
                    setTimeout(() => {
                        openCsatModal(ticketId);
                    }, 400);
                }
            } else {
                showToast(result.message || 'Error al actualizar estado.', 'error');
                if (pendingStatusChange && pendingStatusChange.source === 'modal' && pendingStatusChange.selectEl) {
                    pendingStatusChange.selectEl.value = pendingStatusChange.oldStatusId;
                }
            }
        } catch (err) {
            showToast('Error de conexión al guardar el cambio de estado.', 'error');
            if (pendingStatusChange && pendingStatusChange.source === 'modal' && pendingStatusChange.selectEl) {
                pendingStatusChange.selectEl.value = pendingStatusChange.oldStatusId;
            }
        }
    });

    document.querySelectorAll('.close-status-modal').forEach(btn => {
        btn.addEventListener('click', () => cancelStatusChange());
    });
}

// ==========================================================================
// ENCUESTA DE SATISFACCIÓN (CSAT) - IDÉNTICA AL FORMATO ADJUNTO
// ==========================================================================
function selectCsatRating(rating) {
    selectedCsatRating = parseInt(rating);
    const input = document.getElementById('csat-rating-value');
    if (input) input.value = selectedCsatRating;

    document.querySelectorAll('.csat-emoji-btn').forEach(btn => {
        if (parseInt(btn.dataset.rating) === selectedCsatRating) {
            btn.classList.add('selected');
        } else {
            btn.classList.remove('selected');
        }
    });
}

function openCsatModal(ticketId, ticketCode = '') {
    const modal = document.getElementById('csat-modal');
    if (!modal) return;
    const input = document.getElementById('csat-ticket-id');
    if (input) input.value = ticketId;
    selectCsatRating(5);
    const feedbackInput = document.getElementById('csat-feedback-text');
    if (feedbackInput) feedbackInput.value = '';
    modal.style.display = 'flex';
}

function closeCsatModal() {
    const modal = document.getElementById('csat-modal');
    if (modal) modal.style.display = 'none';
}

function initCsatFormSubmit() {
    const form = document.getElementById('csat-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const ticketId = document.getElementById('csat-ticket-id').value;
        const rating = parseInt(document.getElementById('csat-rating-value').value || 5);
        const feedback = document.getElementById('csat-feedback-text').value.trim();

        if (!ticketId) {
            showToast('Error: No se identificó el ticket.', 'error');
            return;
        }

        try {
            const response = await fetch('api.php?action=submit_csat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({
                    ticket_id: parseInt(ticketId),
                    rating: rating,
                    feedback: feedback,
                    csrf_token: getCsrfToken()
                })
            });

            const result = await response.json();
            if (result.success) {
                showToast('¡Muchas gracias por calificar nuestro servicio de soporte!', 'success');
                closeCsatModal();

                // Si el modal de ticket está abierto, actualizar el banner CSAT
                const banner = document.getElementById('modal-csat-banner');
                if (banner) {
                    banner.style.display = 'block';
                    banner.innerHTML = `
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:8px; padding:0.85rem 1rem; display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <span style="font-size:26px;">${CSAT_EMOJIS[rating] || '⭐'}</span>
                                <div>
                                    <div style="font-size:13px; font-weight:700; color:#166534;">Encuesta de Satisfacción (CSAT): ${CSAT_LABELS[rating]} (${rating}/5)</div>
                                    <div style="font-size:12px; color:#15803D; margin-top:2px;">"${escapeHtml(feedback || 'Sin comentarios adicionales')}"</div>
                                </div>
                            </div>
                            <span class="csat-mandatory-badge" style="background:#DCFCE7; color:#166534; border-color:#86EFAC;">Completada</span>
                        </div>
                    `;
                }
            } else {
                showToast(result.message || 'Error al guardar encuesta.', 'error');
            }
        } catch (err) {
            showToast('Error de conexión al enviar encuesta.', 'error');
        }
    });

    document.querySelectorAll('.close-csat-modal').forEach(btn => {
        btn.addEventListener('click', () => closeCsatModal());
    });
}

function initNewTicketForm() {
    const form = document.getElementById('create-ticket-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('api.php?action=create_ticket', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                },
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                showToast(result.message, 'success');
                form.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast('Error al procesar la subida del ticket y archivo.', 'error');
        }
    });
}

function initSuperAdminForms() {
    const areaForm = document.getElementById('create-area-form');
    if (areaForm) {
        areaForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(areaForm).entries());
            data.csrf_token = getCsrfToken();

            try {
                const response = await fetch('api.php?action=create_area', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    areaForm.reset();
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error al crear el área.', 'error');
            }
        });
    }

    const userForm = document.getElementById('create-user-form');
    if (userForm) {
        userForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(userForm).entries());
            data.csrf_token = getCsrfToken();

            try {
                const response = await fetch('api.php?action=create_user', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    userForm.reset();
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error al registrar colaborador.', 'error');
            }
        });
    }
}

async function loadUnassignedTickets() {
    const tbody = document.getElementById('unassigned-tickets-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1.5rem;">Cargando tickets pendientes...</td></tr>';

    try {
        const areaSelect = document.getElementById('kanban-area-select');
        const areaParam = (areaSelect && parseInt(areaSelect.value) > 0) ? `&area_id=${parseInt(areaSelect.value)}` : (areaSelect ? '&area_id=0' : '');
        const response = await fetch(`api.php?action=get_unassigned_tickets${areaParam}`);
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            tbody.innerHTML = '';
            const isColaborador = (window.CURRENT_USER_ROLE === 'colaborador');
            result.data.forEach(t => {
                const tr = document.createElement('tr');
                const actionButtons = isColaborador ? `
                    <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981;" onclick="pickupTicket(${t.id})">
                        ⚡ Tomar Ticket
                    </button>
                    <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="openTicketModal(${t.id})">
                        Ver Detalle
                    </button>
                ` : `
                    <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981;" onclick="openAssignAgentDialog(${t.id}, ${t.area_id})">
                        👤 Asignar Agente
                    </button>
                    <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="pickupTicket(${t.id})">
                        ⚡ Tomar Ticket
                    </button>
                `;

                tr.innerHTML = `
                    <td>
                        <strong style="cursor:pointer; color:var(--fd-action-btn);" onclick="openTicketModal(${t.id})">${escapeHtml(t.code)}</strong>
                    </td>
                    <td>
                        <a style="cursor:pointer; color:var(--fd-text); text-decoration:none; font-weight:500;" onclick="openTicketModal(${t.id})">
                            ${escapeHtml(t.title)}
                        </a>
                        ${t.attachment_path ? '📎' : ''}
                        <div style="font-size:11px; color:var(--fd-text-dim);">🏢 ${escapeHtml(t.area_name || 'General')}</div>
                    </td>
                    <td>${escapeHtml(t.requester_name)}</td>
                    <td><span class="fd-prio-badge fd-prio-${t.priority}">${t.priority}</span></td>
                    <td style="font-size:12px; color:var(--fd-text-dim);">${t.created_at ? t.created_at.substring(0, 16) : ''}</td>
                    <td>${actionButtons}</td>
                `;
                tbody.appendChild(tr);
            });

            const badge = document.getElementById('inbox-badge-count');
            if (badge) {
                badge.textContent = result.data.length;
                badge.style.display = 'inline-block';
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-muted);">✨ No hay tickets pendientes por asignar en esta área actualmente.</td></tr>';
            const badge = document.getElementById('inbox-badge-count');
            if (badge) {
                badge.style.display = 'none';
            }
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" style="color:var(--danger); text-align:center; padding:1.5rem;">Error al cargar la bandeja de entrada.</td></tr>';
    }
}

async function pickupTicket(ticketId) {
    try {
        const response = await fetch('api.php?action=pickup_ticket', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ ticket_id: parseInt(ticketId), csrf_token: getCsrfToken() })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');

            // Actualizar la fila en las tablas sin forzar recarga destructiva
            document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(row => {
                row.dataset.agentId = window.CURRENT_USER_ID;
                row.dataset.statusId = 2;
                const agentDisplay = row.querySelector('.agent-name-display');
                if (agentDisplay) agentDisplay.textContent = '👤 Mi Usuario';
                
                const statusBadge = row.querySelector('.fd-status-badge');
                if (statusBadge) {
                    statusBadge.textContent = 'En Progreso';
                    statusBadge.className = 'fd-status-badge fd-status-in_progress';
                }

                const pickupBtn = row.querySelector('button[onclick*="pickupTicket"]');
                if (pickupBtn) {
                    pickupBtn.style.display = 'none';
                }
            });

            // Mover la tarjeta en el Tablero Kanban si existe
            const card = document.querySelector(`.ticket-card[data-ticket-id="${ticketId}"]`);
            const targetContainer = document.querySelector(`.tickets-container[data-status-id="2"]`);
            if (card && targetContainer) {
                targetContainer.appendChild(card);
                updateColumnCounters();
            }

            if (typeof loadUnassignedTickets === 'function') {
                loadUnassignedTickets();
            }
            if (typeof loadNotifications === 'function') {
                loadNotifications();
            }
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        showToast('Error al tomar el ticket.', 'error');
    }
}

// APERTURA DEL MODAL CON DROPDOWN DE SELECCIÓN DE AGENTES
async function openAssignAgentDialog(ticketId, targetAreaId = null) {
    const modal = document.getElementById('assign-modal');
    const select = document.getElementById('assign-agent-select');
    const inputTicketId = document.getElementById('assign-ticket-id-input');

    if (!modal || !select) return;

    inputTicketId.value = ticketId;
    select.innerHTML = '<option value="">Cargando lista de usuarios del área...</option>';
    modal.style.display = 'flex';

    try {
        const areaId = targetAreaId || window.CURRENT_USER_AREA_ID || 1;
        const resAgents = await fetch(`api.php?action=get_agents_by_area&area_id=${areaId}`);
        const dataAgents = await resAgents.json();

        if (dataAgents.success && dataAgents.data.length > 0) {
            select.innerHTML = '<option value="">-- Seleccionar Colaborador del Área --</option>';
            dataAgents.data.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = `👤 ${a.name} (${a.email}) - ${a.role_description || a.role_name}`;
                select.appendChild(opt);
            });
        } else {
            select.innerHTML = '<option value="">No hay otros colaboradores en este departamento</option>';
        }
    } catch (err) {
        select.innerHTML = '<option value="">Error al cargar usuarios</option>';
    }
}

function initAssignFormSubmit() {
    const form = document.getElementById('assign-agent-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const ticketId = document.getElementById('assign-ticket-id-input').value;
        const selectAgent = document.getElementById('assign-agent-select');
        const agentId = selectAgent.value;
        const agentName = selectAgent.options[selectAgent.selectedIndex]?.text.split('-')[0].replace('👤', '').trim();

        if (!agentId) {
            showToast('Por favor seleccione un colaborador de la lista.', 'error');
            return;
        }

        try {
            const response = await fetch('api.php?action=assign_agent', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({
                    ticket_id: parseInt(ticketId),
                    agent_id: parseInt(agentId),
                    csrf_token: getCsrfToken()
                })
            });

            const result = await response.json();
            if (result.success) {
                showToast(result.message, 'success');
                document.getElementById('assign-modal').style.display = 'none';
                
                // Actualizar botón a "Reasignar Agente" y estado en las tablas
                document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(row => {
                    row.dataset.agentId = agentId;
                    const agentDisplay = row.querySelector('.agent-name-display');
                    if (agentDisplay) agentDisplay.textContent = agentName;

                    const assignBtn = row.querySelector('button[onclick*="openAssignAgentDialog"]');
                    if (assignBtn) {
                        assignBtn.innerHTML = '🔄 Reasignar Agente';
                        assignBtn.className = 'btn-secondary';
                        assignBtn.style.background = '';
                    }

                    // Actualizar status badge si estaba Por Asignar
                    if (parseInt(row.dataset.statusId) === 1) {
                        row.dataset.statusId = 2;
                        const statusBadge = row.querySelector('.fd-status-badge');
                        if (statusBadge) {
                            statusBadge.textContent = 'En Progreso';
                            statusBadge.className = 'fd-status-badge fd-status-in_progress';
                        }
                    }
                });

                // Mover tarjeta en Tablero Kanban si estaba en Por Asignar
                const card = document.querySelector(`.ticket-card[data-ticket-id="${ticketId}"]`);
                if (card && card.parentElement && parseInt(card.parentElement.dataset.statusId) === 1) {
                    const targetContainer = document.querySelector(`.tickets-container[data-status-id="2"]`);
                    if (targetContainer) {
                        targetContainer.appendChild(card);
                        updateColumnCounters();
                    }
                }

                // Si el modal de ticket está abierto, recargar los detalles
                const modalTicketId = document.getElementById('modal-ticket-id-input')?.value;
                if (modalTicketId && parseInt(modalTicketId) === parseInt(ticketId)) {
                    openTicketModal(ticketId);
                }

                if (typeof loadUnassignedTickets === 'function') {
                    loadUnassignedTickets();
                }

                if (typeof loadNotifications === 'function') {
                    loadNotifications();
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast('Error al asignar el ticket.', 'error');
        }
    });

    document.querySelectorAll('.close-assign-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('assign-modal');
            if (modal) modal.style.display = 'none';
        });
    });
}

// ==========================================================================
// DETALLE DE TICKET CON CSAT Y ASIGNACIÓN DINÁMICA
// ==========================================================================
async function openTicketModal(ticketId) {
    const modal = document.getElementById('ticket-modal');
    if (!modal) return;

    try {
        const response = await fetch(`api.php?action=get_ticket_detail&ticket_id=${ticketId}`);
        const result = await response.json();

        if (result.success) {
            const ticket = result.data;
            currentTicketData = ticket;

            document.getElementById('modal-ticket-title').textContent = `${ticket.code}: ${ticket.title}`;
            document.getElementById('modal-ticket-description').textContent = ticket.description;
            document.getElementById('modal-ticket-requester').textContent = `${ticket.requester_name} (${ticket.requester_email})`;
            document.getElementById('modal-ticket-agent').textContent = ticket.agent_name || 'Sin Asignar';
            document.getElementById('modal-ticket-area').textContent = ticket.area_name;
            document.getElementById('modal-ticket-id-input').value = ticket.id;

            const statusPill = document.getElementById('modal-ticket-status-pill');
            if (statusPill) {
                statusPill.textContent = ticket.status_name || 'Por Asignar';
                statusPill.className = `fd-status-badge fd-status-${ticket.status_slug || 'unassigned'}`;
            }

            const prioPill = document.getElementById('modal-ticket-prio-pill');
            if (prioPill) {
                prioPill.textContent = ticket.priority || 'medium';
                prioPill.className = `fd-prio-badge fd-prio-${ticket.priority || 'medium'}`;
            }

            const avatarEl = document.getElementById('modal-author-avatar');
            if (avatarEl) {
                avatarEl.textContent = (ticket.requester_name || 'U').substring(0, 2).toUpperCase();
            }

            const dateEl = document.getElementById('modal-ticket-date');
            if (dateEl) {
                dateEl.textContent = ticket.created_at ? `Creado el ${ticket.created_at.substring(0, 16)}` : '';
            }

            // Permisos para editar propiedades (Admin, Manager de esa área o Agente asignado)
            const canEditProps = (window.CURRENT_USER_ROLE === 'admin' || (window.CURRENT_USER_ROLE === 'manager' && parseInt(ticket.area_id) === parseInt(window.CURRENT_USER_AREA_ID)) || (ticket.assigned_agent_id && parseInt(ticket.assigned_agent_id) === parseInt(window.CURRENT_USER_ID)));

            const statusSelect = document.getElementById('modal-prop-status-select');
            if (statusSelect) {
                statusSelect.value = ticket.status_id;
                statusSelect.disabled = !canEditProps;
                statusSelect.style.cursor = canEditProps ? 'pointer' : 'not-allowed';
                statusSelect.title = canEditProps ? 'Modificar estado (requiere comentario obligatorio)' : 'Solo agentes asignados o coordinadores pueden modificar el estado';
            }

            const prioSelect = document.getElementById('modal-prop-priority-select');
            if (prioSelect) {
                prioSelect.value = ticket.priority;
                prioSelect.disabled = !canEditProps;
                prioSelect.style.cursor = canEditProps ? 'pointer' : 'not-allowed';
                prioSelect.title = canEditProps ? 'Modificar prioridad' : 'Solo agentes asignados o coordinadores pueden modificar la prioridad';
            }

            // BOTÓN DINÁMICO: "Asignar Agente" si no tiene nadie asignado, "Reasignar Agente" si ya tiene alguien
            const assignContainer = document.getElementById('modal-assign-btn-container');
            const assignBtn = document.getElementById('modal-assign-trigger-btn');
            const canAssign = (window.CURRENT_USER_ROLE === 'admin' || (window.CURRENT_USER_ROLE === 'manager' && parseInt(ticket.area_id) === parseInt(window.CURRENT_USER_AREA_ID)));
            const canPickup = (!ticket.assigned_agent_id && (window.CURRENT_USER_ROLE === 'colaborador' || window.CURRENT_USER_ROLE === 'agent') && parseInt(ticket.area_id) === parseInt(window.CURRENT_USER_AREA_ID));

            if (assignContainer && assignBtn) {
                if (canAssign) {
                    assignContainer.style.display = 'inline-block';
                    if (!ticket.assigned_agent_id) {
                        assignBtn.innerHTML = '👤 Asignar Agente';
                        assignBtn.className = 'btn-primary';
                        assignBtn.style.backgroundColor = '#10B981';
                    } else {
                        assignBtn.innerHTML = '🔄 Reasignar Agente';
                        assignBtn.className = 'btn-secondary';
                        assignBtn.style.backgroundColor = '';
                    }
                    assignBtn.onclick = (e) => {
                        if (e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        openAssignAgentDialog(ticket.id, ticket.area_id);
                    };
                } else if (canPickup) {
                    assignContainer.style.display = 'inline-block';
                    assignBtn.innerHTML = '⚡ Tomar Ticket';
                    assignBtn.className = 'btn-primary';
                    assignBtn.style.backgroundColor = '#10B981';
                    assignBtn.onclick = async (e) => {
                        if (e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        await pickupTicket(ticket.id);
                        openTicketModal(ticket.id);
                    };
                } else {
                    assignContainer.style.display = 'none';
                }
            }

            // BANNER DE ENCUESTA CSAT
            const csatBanner = document.getElementById('modal-csat-banner');
            if (csatBanner) {
                if (ticket.survey) {
                    csatBanner.style.display = 'block';
                    const rating = parseInt(ticket.survey.rating);
                    csatBanner.innerHTML = `
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:8px; padding:0.85rem 1rem; display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <span style="font-size:26px;">${CSAT_EMOJIS[rating] || '⭐'}</span>
                                <div>
                                    <div style="font-size:13px; font-weight:700; color:#166534;">Encuesta de Satisfacción (CSAT): ${CSAT_LABELS[rating] || ''} (${rating}/5)</div>
                                    <div style="font-size:12px; color:#15803D; margin-top:2px;">"${escapeHtml(ticket.survey.feedback || 'Sin comentarios adicionales')}"</div>
                                </div>
                            </div>
                            <span class="csat-mandatory-badge" style="background:#DCFCE7; color:#166534; border-color:#86EFAC;">Completada</span>
                        </div>
                    `;
                } else if (parseInt(ticket.status_id) === 4) {
                    csatBanner.style.display = 'block';
                    const isRequesterOrAdmin = (parseInt(ticket.requester_id) === parseInt(window.CURRENT_USER_ID)) || (window.CURRENT_USER_ROLE === 'admin');
                    if (isRequesterOrAdmin) {
                        csatBanner.innerHTML = `
                            <div style="background:#FEF3C7; border:1px solid #FDE68A; border-radius:8px; padding:0.85rem 1rem; display:flex; align-items:center; justify-content:space-between;">
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <span style="font-size:24px;">⭐</span>
                                    <div>
                                        <div style="font-size:13px; font-weight:700; color:#92400E;">Ticket Resuelto - Encuesta de Satisfacción Pendiente</div>
                                        <div style="font-size:12px; color:#B45309;">Ayúdanos a mejorar calificando tu experiencia con el soporte recibido.</div>
                                    </div>
                                </div>
                                <button type="button" class="btn-primary" style="background:#D97706; font-size:12px; padding:0.4rem 0.8rem;" onclick="openCsatModal(${ticket.id})">
                                    ⭐ Calificar Servicio
                                </button>
                            </div>
                        `;
                    } else {
                        csatBanner.innerHTML = `
                            <div style="background:#F1F5F9; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem 1rem; display:flex; align-items:center; justify-content:space-between;">
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <span style="font-size:24px;">ℹ️</span>
                                    <div>
                                        <div style="font-size:13px; font-weight:700; color:#334155;">Ticket Resuelto</div>
                                        <div style="font-size:12px; color:#64748b;">Pendiente de calificación por parte del solicitante (${escapeHtml(ticket.requester_name)}).</div>
                                    </div>
                                </div>
                                <span class="csat-mandatory-badge" style="background:#E2E8F0; color:#475569; border-color:#CBD5E1;">Pendiente Calificación</span>
                            </div>
                        `;
                    }
                } else {
                    csatBanner.style.display = 'none';
                    csatBanner.innerHTML = '';
                }
            }

            // Archivo Adjunto
            const attachmentContainer = document.getElementById('modal-attachment-container');
            const attachmentContent = document.getElementById('modal-attachment-content');

            if (ticket.attachment_path) {
                attachmentContainer.style.display = 'block';
                const fileExt = ticket.attachment_path.split('.').pop().toLowerCase();

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                    attachmentContent.innerHTML = `
                        <a href="${ticket.attachment_path}" target="_blank">
                            <img src="${ticket.attachment_path}" alt="Captura de pantalla" style="max-width:100%; max-height:280px; border-radius:8px; border:1px solid var(--border-subtle); margin-top:0.4rem;">
                        </a>
                        <div style="margin-top:0.3rem;"><a href="${ticket.attachment_path}" target="_blank" style="color:#0284c7; font-size:12px;">🔎 Ver captura en tamaño completo</a></div>
                    `;
                } else {
                    attachmentContent.innerHTML = `
                        <a href="${ticket.attachment_path}" target="_blank" class="btn-secondary" style="font-size:12px; padding:0.4rem 0.8rem;">
                            📄 Descargar Documento Adjunto (${fileExt.toUpperCase()})
                        </a>
                    `;
                }
            } else {
                attachmentContainer.style.display = 'none';
                attachmentContent.innerHTML = '';
            }

            loadTicketComments(ticket.id);
            modal.style.display = 'flex';
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        showToast('No se pudo cargar la información del ticket.', 'error');
    }
}

async function loadTicketComments(ticketId) {
    const container = document.getElementById('comments-container');
    container.innerHTML = '<p style="color:var(--text-muted)">Cargando conversación...</p>';

    try {
        const response = await fetch(`api.php?action=get_comments&ticket_id=${ticketId}`);
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            container.innerHTML = '';
            result.data.forEach(c => {
                const isInternal = c.is_internal == 1;
                const div = document.createElement('div');
                div.className = `comment-bubble ${isInternal ? 'internal-note' : ''}`;
                div.innerHTML = `
                    <div class="comment-meta">
                        <span><strong>${escapeHtml(c.user_name)}</strong> (${c.role_name}) ${isInternal ? '<em style="color:#f59e0b">🔒 Nota Privada Interna</em>' : ''}</span>
                        <span>${c.created_at}</span>
                    </div>
                    <div>${escapeHtml(c.comment)}</div>
                `;
                container.appendChild(div);
            });
        } else {
            container.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem">No hay comentarios aún.</p>';
        }
    } catch (err) {
        container.innerHTML = '<p style="color:var(--danger)">Error al cargar comentarios.</p>';
    }
}

async function submitComment(isInternal = false) {
    const ticketId = document.getElementById('modal-ticket-id-input').value;
    const textarea = document.getElementById('new-comment-text');
    const comment = textarea.value.trim();

    if (!comment) return;

    try {
        const response = await fetch('api.php?action=add_comment', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({
                ticket_id: parseInt(ticketId),
                comment: comment,
                is_internal: isInternal,
                csrf_token: getCsrfToken()
            })
        });

        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            textarea.value = '';
            loadTicketComments(ticketId);
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        showToast('Error al publicar comentario.', 'error');
    }
}

function initAssignModal() {
    document.querySelectorAll('.close-modal, .close-assign-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            if (pendingStatusChange) cancelStatusChange();
            document.querySelectorAll('.modal-backdrop').forEach(m => m.style.display = 'none');
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                if (pendingStatusChange) cancelStatusChange();
                backdrop.style.display = 'none';
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (pendingStatusChange) cancelStatusChange();
            document.querySelectorAll('.modal-backdrop').forEach(m => m.style.display = 'none');
            toggleNotificationsDropdown(false);
        } else if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault();
            const activeSearch = document.querySelector('.tab-content.active .fd-search-input') || document.getElementById('tickets-search-input') || document.getElementById('myreq-search-input');
            if (activeSearch) {
                activeSearch.focus();
                activeSearch.select();
            }
        }
    });

    // Clic fuera del dropdown de notificaciones para cerrarlo
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('topbar-notif-dropdown');
        const bellBtn = document.getElementById('topbar-notif-btn');
        if (dropdown && dropdown.style.display === 'flex' && bellBtn && !bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function updateColumnCounters() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const count = col.querySelectorAll('.ticket-card').length;
        const countBadge = col.querySelector('.ticket-count');
        if (countBadge) countBadge.textContent = count;
    });
}

function showToast(message, type = 'success') {
    let toast = document.getElementById('toast-notification');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast-notification';
        document.body.appendChild(toast);
    }

    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    toast.innerHTML = `
        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
            <div style="display:flex; align-items:center; gap:0.6rem;">
                <span style="font-size:16px;">${icons[type] || 'ℹ️'}</span>
                <span>${escapeHtml(message)}</span>
            </div>
            <button onclick="this.parentElement.parentElement.style.display='none'" style="background:none; border:none; color:inherit; font-size:16px; cursor:pointer; padding:0 4px; opacity:0.7;">&times;</button>
        </div>
    `;
    toast.className = `toast-${type}`;
    toast.style.display = 'block';

    if (window._toastTimeout) clearTimeout(window._toastTimeout);
    window._toastTimeout = setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}

// ==========================================================================
// FILTROS Y BÚSQUEDA DINÁMICA INTEGRADA
// ==========================================================================
function initGlobalSearch() {
    const ticketsInput = document.getElementById('tickets-search-input');
    if (ticketsInput) {
        ticketsInput.addEventListener('input', (e) => filterTicketsGlobal(e.target.value));
    }

    const myreqInput = document.getElementById('myreq-search-input');
    if (myreqInput) {
        myreqInput.addEventListener('input', (e) => filterMyRequests(e.target.value));
    }

    const inboxInput = document.getElementById('inbox-search-input');
    if (inboxInput) {
        inboxInput.addEventListener('input', (e) => filterInboxTickets(e.target.value));
    }
}

function filterTicketsGlobal(query) {
    query = (query || '').toLowerCase().trim();

    // 1. Filtrar tabla de Todos los Tickets
    const tableRows = document.querySelectorAll('#all-tickets-tbody tr.ticket-row');
    let visibleTableCount = 0;
    tableRows.forEach(r => {
        const text = r.textContent.toLowerCase();
        const matches = text.includes(query);
        r.style.display = matches ? '' : 'none';
        if (matches) visibleTableCount++;
    });

    let emptyRow = document.getElementById('all-tickets-empty-search');
    if (visibleTableCount === 0 && tableRows.length > 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'all-tickets-empty-search';
            emptyRow.innerHTML = '<td colspan="8" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">🔍 No se encontraron tickets que coincidan con la búsqueda.</td>';
            const tbody = document.getElementById('all-tickets-tbody');
            if (tbody) tbody.appendChild(emptyRow);
        } else {
            emptyRow.style.display = '';
        }
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }

    // 2. Filtrar tarjetas del Tablero Kanban
    const kanbanCards = document.querySelectorAll('.kanban-board .ticket-card');
    kanbanCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(query) ? '' : 'none';
    });
    updateColumnCounters();
}

function filterMyRequests(query) {
    query = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#my-requests-table tbody tr.ticket-row');
    let visibleCount = 0;
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        const matches = text.includes(query);
        r.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });

    let emptyRow = document.getElementById('my-requests-empty-search');
    if (visibleCount === 0 && rows.length > 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'my-requests-empty-search';
            emptyRow.innerHTML = '<td colspan="8" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">🔍 No se encontraron solicitudes que coincidan con la búsqueda.</td>';
            const tbody = document.querySelector('#my-requests-table tbody');
            if (tbody) tbody.appendChild(emptyRow);
        } else {
            emptyRow.style.display = '';
        }
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
}

function filterInboxTickets(query) {
    query = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#unassigned-tickets-tbody tr');
    let visibleCount = 0;
    rows.forEach(r => {
        if (r.id === 'inbox-empty-search') return;
        const text = r.textContent.toLowerCase();
        const matches = text.includes(query);
        r.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });

    let emptyRow = document.getElementById('inbox-empty-search');
    if (visibleCount === 0 && rows.length > 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'inbox-empty-search';
            emptyRow.innerHTML = '<td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">🔍 No se encontraron tickets pendientes que coincidan con la búsqueda.</td>';
            const tbody = document.getElementById('unassigned-tickets-tbody');
            if (tbody) tbody.appendChild(emptyRow);
        } else {
            emptyRow.style.display = '';
        }
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
}

// ==========================================================================
// CENTRO DE NOTIFICACIONES EN TIEMPO REAL
// ==========================================================================
function initNotifications() {
    loadNotifications();
    // Actualizar notificaciones cada 45 segundos
    setInterval(loadNotifications, 45000);
}

async function loadNotifications() {
    try {
        const res = await fetch('api.php?action=get_notifications');
        const data = await res.json();
        if (data.success) {
            updateNotificationUI(data.data, data.unread_count);
        }
    } catch (e) {
        console.error('Error al cargar notificaciones:', e);
    }
}

function updateNotificationUI(notifications, unreadCount) {
    const badge = document.getElementById('topbar-notif-badge');
    const list = document.getElementById('topbar-notif-list');
    const headerCount = document.getElementById('topbar-notif-count');

    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }

    if (headerCount) {
        headerCount.textContent = `${unreadCount} nuevas`;
    }

    if (list) {
        if (!notifications || notifications.length === 0) {
            list.innerHTML = '<div class="fd-notif-empty">✨ No tienes notificaciones pendientes.</div>';
            return;
        }

        list.innerHTML = notifications.map(n => `
            <div class="fd-notif-item unread" onclick="handleNotificationClick(${n.ticket_id})">
                <div class="fd-notif-icon">${n.icon || '📌'}</div>
                <div class="fd-notif-content">
                    <div class="fd-notif-title">${escapeHtml(n.title)}</div>
                    <div class="fd-notif-desc">${escapeHtml(n.message)}</div>
                    <div class="fd-notif-time">${escapeHtml(n.time ? n.time.substring(0, 16) : '')}</div>
                </div>
            </div>
        `).join('');
    }
}

function handleNotificationClick(ticketId) {
    toggleNotificationsDropdown(false);
    if (ticketId) {
        openTicketModal(ticketId);
    }
}

function toggleNotificationsDropdown(forceState = null) {
    const dropdown = document.getElementById('topbar-notif-dropdown');
    if (!dropdown) return;
    const isVisible = dropdown.style.display === 'flex';
    const newState = (forceState !== null) ? forceState : !isVisible;
    dropdown.style.display = newState ? 'flex' : 'none';
    if (newState) {
        loadNotifications();
    }
}

async function toggleUserRole(userId, newRoleId) {
    try {
        const response = await fetch('api.php?action=toggle_user_role', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({
                user_id: parseInt(userId),
                role_id: parseInt(newRoleId),
                csrf_token: getCsrfToken()
            })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) {
        showToast('Error al actualizar rol del usuario.', 'error');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

window.openCsatModal = openCsatModal;
window.closeCsatModal = closeCsatModal;
window.selectCsatRating = selectCsatRating;
window.pickupTicket = pickupTicket;
window.openAssignAgentDialog = openAssignAgentDialog;
window.openTicketModal = openTicketModal;
window.loadUnassignedTickets = loadUnassignedTickets;
window.loadNotifications = loadNotifications;
window.toggleNotificationsDropdown = toggleNotificationsDropdown;
window.handleNotificationClick = handleNotificationClick;
window.toggleUserRole = toggleUserRole;
window.filterTicketsGlobal = filterTicketsGlobal;
window.filterMyRequests = filterMyRequests;
window.filterInboxTickets = filterInboxTickets;
window.showToast = showToast;

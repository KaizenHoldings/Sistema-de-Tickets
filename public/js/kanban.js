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

function getCsatStarRatingHtml(rating, max = 5) {
    let html = '<span style="display:inline-flex; align-items:center; gap:2px;">';
    for (let i = 1; i <= max; i++) {
        if (i <= rating) {
            html += '<svg width="15" height="15" viewBox="0 0 24 24" fill="#F59E0B" stroke="#D97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        } else {
            html += '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        }
    }
    html += '</span>';
    return html;
}

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
    initTicketRowClickSelection();
});

function getCsrfToken() {
    return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function initNavigationTabs() {
    const tabs = document.querySelectorAll('.nav-tab');

    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            const targetId = tab.dataset.target;
            if (typeof switchNavTab === 'function') {
                switchNavTab(targetId);
                return;
            }

            const contents = document.querySelectorAll('.tab-content');
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            tab.classList.add('active');
            const targetContent = document.getElementById(targetId);
            if (targetContent) targetContent.classList.add('active');

            if (targetId === 'tickets-tab') {
                const heading = document.getElementById('tickets-view-heading');
                if (heading) heading.textContent = 'Todos los tickets';
                if (typeof applyAllFilters === 'function') {
                    applyAllFilters(true);
                }
            }

            if (targetId === 'inbox-tab' && typeof loadUnassignedTickets === 'function') {
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

            // Al arrastrar en Kanban, abrir el modal de ticket con el nuevo estado seleccionado para guardar con SharePoint
            openTicketModal(ticketId).then(() => {
                syncModalStatusFromId(newStatusId);
                showToast(`Estado ajustado a '${STATUS_ID_TO_SP[newStatusId] || 'nuevo estado'}'. Presione 'Guardar en SharePoint' para aplicar.`, 'info');
            });
        });
    });
}

const STATUS_ID_TO_SP = {
    1: 'Pendiente',
    2: 'En proceso',
    3: 'Stand By',
    4: 'Procesado'
};

const SP_TO_STATUS_ID = {
    'pendiente': 1,
    'en proceso': 2,
    'en_proceso': 2,
    'stand by': 3,
    'stand_by': 3,
    'procesado': 4
};

function updateModalStatusPill(spStatus) {
    const statusPill = document.getElementById('modal-ticket-status-pill');
    if (!statusPill) return;
    statusPill.textContent = spStatus;
    let statusClass = 'fd-status-pendiente';
    const lower = (spStatus || '').toLowerCase();
    if (lower.includes('procesado')) statusClass = 'fd-status-procesado';
    else if (lower.includes('en proceso') || lower.includes('en_proceso')) statusClass = 'fd-status-en-proceso';
    else if (lower.includes('stand by') || lower.includes('stand_by')) statusClass = 'fd-status-stand-by';
    statusPill.className = `fd-status-badge ${statusClass}`;
}

function syncModalStatusFromId(statusId) {
    const id = parseInt(statusId);
    const spStatus = STATUS_ID_TO_SP[id] || 'Pendiente';
    const spSelect = document.getElementById('modal-sp-status-select');
    if (spSelect) spSelect.value = spStatus;
    updateModalStatusPill(spStatus);
}

function syncModalStatusFromName(spStatus) {
    const lower = (spStatus || '').toLowerCase().trim();
    const statusId = SP_TO_STATUS_ID[lower] || 1;
    const propSelect = document.getElementById('modal-prop-status-select');
    if (propSelect) propSelect.value = statusId;
    updateModalStatusPill(spStatus);
}

// Sincronizar cambio de estado desde el dropdown de propiedades en el modal sin abrir modal emergente
function handleDetailStatusChange(selectEl) {
    if (!currentTicketData) return;
    const newStatusId = parseInt(selectEl.value);
    syncModalStatusFromId(newStatusId);
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
                                ${getCsatStarRatingHtml(rating)}
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
                setTimeout(() => {
                    const activeContent = document.querySelector('.tab-content.active');
                    const tab = activeContent ? activeContent.id : 'tickets-tab';
                    window.location.href = `index.php?tab=${encodeURIComponent(tab)}&page=1`;
                }, 1500);
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
                    setTimeout(() => {
                        window.location.href = 'index.php?tab=admin-tab';
                    }, 1000);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error al crear el área.', 'error');
            }
        });
    }

    // Formulario Modal Crear Usuario
    const modalCreateUserForm = document.getElementById('modal-create-user-form');
    if (modalCreateUserForm) {
        modalCreateUserForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(modalCreateUserForm).entries());
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
                    closeCreateUserModal();
                    setTimeout(() => {
                        window.location.href = 'index.php?tab=admin-tab';
                    }, 900);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error al registrar colaborador.', 'error');
            }
        });
    }

    // Formulario Modal Editar Usuario
    const modalEditUserForm = document.getElementById('modal-edit-user-form');
    if (modalEditUserForm) {
        modalEditUserForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(modalEditUserForm).entries());
            data.csrf_token = getCsrfToken();

            try {
                const response = await fetch('api.php?action=update_user', {
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
                    closeEditUserModal();
                    setTimeout(() => {
                        window.location.href = 'index.php?tab=admin-tab';
                    }, 900);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error al actualizar datos del usuario.', 'error');
            }
        });
    }

    // Formulario Modal Restablecer Contraseña
    const modalResetPwdForm = document.getElementById('modal-reset-password-form');
    if (modalResetPwdForm) {
        modalResetPwdForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const pwd = document.getElementById('reset-new-password')?.value || '';
            const confirmPwd = document.getElementById('reset-confirm-password')?.value || '';

            if (pwd !== confirmPwd) {
                showToast('Las contraseñas no coinciden.', 'error');
                return;
            }

            if (pwd.length < 6) {
                showToast('La contraseña debe tener al menos 6 caracteres.', 'error');
                return;
            }

            const data = Object.fromEntries(new FormData(modalResetPwdForm).entries());
            data.csrf_token = getCsrfToken();

            try {
                const response = await fetch('api.php?action=reset_user_password', {
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
                    closeResetPasswordModal();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Error al restablecer la contraseña.', 'error');
            }
        });
    }
}

function switchAdminSection(section) {
    const usersView = document.getElementById('admin-users-view');
    const areasView = document.getElementById('admin-areas-view');
    const btnUsers = document.getElementById('tab-btn-users');
    const btnAreas = document.getElementById('tab-btn-areas');

    if (section === 'areas') {
        if (usersView) usersView.style.display = 'none';
        if (areasView) areasView.style.display = 'block';
        if (btnUsers) btnUsers.classList.remove('active');
        if (btnAreas) btnAreas.classList.add('active');
    } else {
        if (usersView) usersView.style.display = 'block';
        if (areasView) areasView.style.display = 'none';
        if (btnUsers) btnUsers.classList.add('active');
        if (btnAreas) btnAreas.classList.remove('active');
    }
}

function filterUsersTable() {
    const searchInput = document.getElementById('user-search-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

    const areaSelect = document.getElementById('user-filter-area');
    const selectedArea = areaSelect ? areaSelect.value.toLowerCase().trim() : '';

    const roleSelect = document.getElementById('user-filter-role');
    const selectedRole = roleSelect ? roleSelect.value.trim() : '';

    const statusSelect = document.getElementById('user-filter-status');
    const selectedStatus = statusSelect ? statusSelect.value.trim() : '';

    const rows = document.querySelectorAll('#users-management-table tbody tr.user-row');

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const email = row.dataset.email || '';
        const area = (row.dataset.area || '').toLowerCase();
        const role = row.dataset.role || '';
        const active = row.dataset.active || '';

        const matchQuery = !query || name.includes(query) || email.includes(query);
        const matchArea = !selectedArea || area.includes(selectedArea);
        const matchRole = !selectedRole || role === selectedRole;
        const matchStatus = !selectedStatus || active === selectedStatus;

        if (matchQuery && matchArea && matchRole && matchStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openCreateUserModal() {
    const modal = document.getElementById('create-user-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        const form = document.getElementById('modal-create-user-form');
        if (form) form.reset();
    }
}

function closeCreateUserModal() {
    const modal = document.getElementById('create-user-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

function openEditUserModal(user) {
    const modal = document.getElementById('edit-user-modal');
    if (!modal) return;

    const idInput = document.getElementById('edit-user-id');
    const nameInput = document.getElementById('edit-user-name');
    const emailInput = document.getElementById('edit-user-email');
    const areaInput = document.getElementById('edit-user-area-id');
    const roleInput = document.getElementById('edit-user-role-id');
    const pwdInput = document.getElementById('edit-user-password');

    if (idInput) idInput.value = user.id;
    if (nameInput) nameInput.value = user.name || '';
    if (emailInput) emailInput.value = user.email || '';
    if (areaInput) areaInput.value = user.area_id || '';
    if (roleInput) roleInput.value = user.role_id || '3';
    if (pwdInput) pwdInput.value = '';

    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeEditUserModal() {
    const modal = document.getElementById('edit-user-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

function openResetPasswordModal(userId, userName) {
    const modal = document.getElementById('reset-password-modal');
    if (!modal) return;

    const idInput = document.getElementById('reset-pwd-user-id');
    const nameLabel = document.getElementById('reset-pwd-user-name');
    const pwdInput = document.getElementById('reset-new-password');
    const confirmInput = document.getElementById('reset-confirm-password');

    if (idInput) idInput.value = userId;
    if (nameLabel) nameLabel.textContent = userName || ('Usuario #' + userId);
    if (pwdInput) pwdInput.value = '';
    if (confirmInput) confirmInput.value = '';

    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeResetPasswordModal() {
    const modal = document.getElementById('reset-password-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

async function toggleUserStatus(userId, userName, currentStatus) {
    const actionText = currentStatus === 1 ? 'desactivar' : 'activar';
    if (!confirm(`¿Está seguro de que desea ${actionText} la cuenta de "${userName}"?`)) {
        return;
    }

    try {
        const response = await fetch('api.php?action=toggle_user_status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({
                user_id: parseInt(userId),
                csrf_token: getCsrfToken()
            })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => {
                window.location.href = 'index.php?tab=admin-tab';
            }, 800);
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) {
        showToast('Error al cambiar el estado del usuario.', 'error');
    }
}

async function deleteUser(userId, userName) {
    if (!confirm(`¿Está seguro de que desea eliminar permanentemente al usuario "${userName}"?\n\nNota: Si el usuario tiene tickets o comentarios asociados, la eliminación será rechazada por integridad referencial.`)) {
        return;
    }

    try {
        const response = await fetch('api.php?action=delete_user', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({
                user_id: parseInt(userId),
                csrf_token: getCsrfToken()
            })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => {
                window.location.href = 'index.php?tab=admin-tab';
            }, 900);
        } else {
            alert(result.message);
        }
    } catch (e) {
        showToast('Error al procesar la eliminación del usuario.', 'error');
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
                tr.className = 'ticket-row';
                tr.dataset.ticketId = t.id;
                const actionButtons = isColaborador ? `
                    <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981; display:inline-flex; align-items:center; gap:0.3rem;" onclick="pickupTicket(${t.id})">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        Tomar Ticket
                    </button>
                    <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem;" onclick="openTicketModal(${t.id})">
                        Ver Detalle
                    </button>
                ` : `
                    <button class="btn-primary" style="padding:0.35rem 0.75rem; font-size:12px; background:#10B981; display:inline-flex; align-items:center; gap:0.3rem;" onclick="openAssignAgentDialog(${t.id}, ${t.area_id})">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        Asignar Agente
                    </button>
                    <button class="btn-secondary" style="padding:0.35rem 0.75rem; font-size:12px; margin-left:0.35rem; display:inline-flex; align-items:center; gap:0.3rem;" onclick="pickupTicket(${t.id})">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        Tomar Ticket
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
                        ${t.attachment_path ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-left:4px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>' : ''}
                        <div style="font-size:11px; color:var(--fd-text-dim);">${escapeHtml(t.area_name || 'General')}</div>
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
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-muted);">No hay tickets pendientes por asignar en esta área actualmente.</td></tr>';
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
                if (agentDisplay) agentDisplay.textContent = 'Mi Usuario';
                
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
                opt.textContent = `${a.name} (${a.email}) - ${a.role_description || a.role_name}`;
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
        const agentName = selectAgent.options[selectAgent.selectedIndex]?.text.split('-')[0].trim();

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

                // Actualizar campo unificado de responsable en el modal principal si está abierto
                const spRespInput = document.getElementById('modal-sp-responsable-input');
                if (spRespInput) spRespInput.value = agentName;
                const agentSpan = document.getElementById('modal-ticket-agent');
                if (agentSpan) agentSpan.textContent = agentName;
                
                // Actualizar botón a "Reasignar Agente" y estado en las tablas
                document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(row => {
                    row.dataset.agentId = agentId;
                    const agentDisplay = row.querySelector('.agent-name-display');
                    if (agentDisplay) agentDisplay.textContent = agentName;

                    const assignBtn = row.querySelector('button[onclick*="openAssignAgentDialog"]');
                    if (assignBtn) {
                        assignBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>Reasignar Agente';
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

    // Resaltar visualmente la fila del ticket seleccionado en todas las tablas
    document.querySelectorAll('tr.ticket-row-selected').forEach(r => r.classList.remove('ticket-row-selected'));
    document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(r => r.classList.add('ticket-row-selected'));

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

            // Campos de Edición SharePoint
            const spIdTag = document.getElementById('modal-sp-id-tag');
            if (spIdTag) {
                if (ticket.sharepoint_id && parseInt(ticket.sharepoint_id) > 0) {
                    spIdTag.textContent = `ID SharePoint: #${ticket.sharepoint_id}`;
                    spIdTag.style.background = '#E0F2FE';
                    spIdTag.style.color = '#0369A1';
                } else {
                    spIdTag.textContent = `Local (Sin SharePoint)`;
                    spIdTag.style.background = '#F1F5F9';
                    spIdTag.style.color = '#64748B';
                }
            }

            const exportBtn = document.getElementById('btn-export-to-sharepoint');
            if (exportBtn) {
                if (!ticket.sharepoint_id || parseInt(ticket.sharepoint_id) <= 0) {
                    exportBtn.style.display = 'inline-flex';
                    exportBtn.onclick = () => exportCurrentTicketToSharePoint(ticket.id);
                } else {
                    exportBtn.style.display = 'none';
                }
            }

            const spStatus = ticket.sp_status || ticket.status_name || 'Pendiente';
            const spStatusSelect = document.getElementById('modal-sp-status-select');
            if (spStatusSelect) spStatusSelect.value = spStatus;

            const spCompanyInput = document.getElementById('modal-sp-company-input');
            if (spCompanyInput) spCompanyInput.value = ticket.company_name || ticket.requester_name || '';

            const spAreaSelect = document.getElementById('modal-sp-area-select');
            if (spAreaSelect) spAreaSelect.value = ticket.area_code || ((parseInt(ticket.area_id) === 3) ? 'SSGG' : 'IT');

            const spRespInput = document.getElementById('modal-sp-responsable-input');
            if (spRespInput) spRespInput.value = ticket.responsable || ticket.agent_name || '';

            const spTitleInput = document.getElementById('modal-sp-title-input');
            if (spTitleInput) spTitleInput.value = ticket.title || '';

            const spObsInput = document.getElementById('modal-sp-observations-input');
            if (spObsInput) spObsInput.value = ticket.observations || '';

            const spFeedback = document.getElementById('modal-sp-save-feedback');
            if (spFeedback) spFeedback.style.display = 'none';

            const statusPill = document.getElementById('modal-ticket-status-pill');
            if (statusPill) {
                statusPill.textContent = spStatus;
                let statusClass = 'fd-status-pendiente';
                const lowerStatus = spStatus.toLowerCase();
                if (lowerStatus.includes('procesado')) statusClass = 'fd-status-procesado';
                else if (lowerStatus.includes('en proceso') || lowerStatus.includes('en_proceso')) statusClass = 'fd-status-en-proceso';
                else if (lowerStatus.includes('stand by') || lowerStatus.includes('stand_by')) statusClass = 'fd-status-stand-by';
                statusPill.className = `fd-status-badge ${statusClass}`;
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

            const dueDateEl = document.getElementById('modal-ticket-due-date');
            if (dueDateEl) {
                dueDateEl.textContent = ticket.due_date ? ticket.due_date.substring(0, 16) : '—';
            }

            const resolvedDateEl = document.getElementById('modal-ticket-resolved-date');
            const resolvedPill = document.getElementById('modal-resolved-date-pill');
            if (resolvedDateEl) {
                resolvedDateEl.textContent = ticket.resolved_at ? ticket.resolved_at.substring(0, 16) : '—';
                if (resolvedPill) {
                    resolvedPill.style.display = ticket.resolved_at ? 'inline-block' : 'none';
                }
            }

            // Permisos para editar propiedades (Admin, Manager de esa área o Agente asignado)
            const canEditProps = (window.CURRENT_USER_ROLE === 'admin' || (window.CURRENT_USER_ROLE === 'manager' && parseInt(ticket.area_id) === parseInt(window.CURRENT_USER_AREA_ID)) || (ticket.assigned_agent_id && parseInt(ticket.assigned_agent_id) === parseInt(window.CURRENT_USER_ID)));

            const statusSelect = document.getElementById('modal-prop-status-select');
            if (statusSelect) {
                statusSelect.value = ticket.status_id;
                statusSelect.disabled = !canEditProps;
                statusSelect.style.cursor = canEditProps ? 'pointer' : 'not-allowed';
                statusSelect.title = canEditProps ? 'Modificar estado' : 'Solo agentes asignados o coordinadores pueden modificar el estado';
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
                        assignBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Asignar Agente';
                        assignBtn.className = 'btn-primary';
                        assignBtn.style.backgroundColor = '#10B981';
                    } else {
                        assignBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>Reasignar Agente';
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
                    assignBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>Tomar Ticket';
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
                                ${getCsatStarRatingHtml(rating)}
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
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#F59E0B" stroke="#D97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    <div>
                                        <div style="font-size:13px; font-weight:700; color:#92400E;">Ticket Resuelto - Encuesta de Satisfacción Pendiente</div>
                                        <div style="font-size:12px; color:#B45309;">Ayúdanos a mejorar calificando tu experiencia con el soporte recibido.</div>
                                    </div>
                                </div>
                                <button type="button" class="btn-primary" style="background:#D97706; font-size:12px; padding:0.4rem 0.8rem; display:inline-flex; align-items:center; gap:0.35rem;" onclick="openCsatModal(${ticket.id})">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    Calificar Servicio
                                </button>
                            </div>
                        `;
                    } else {
                        csatBanner.innerHTML = `
                            <div style="background:#F1F5F9; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem 1rem; display:flex; align-items:center; justify-content:space-between;">
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
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
                        <div style="margin-top:0.3rem;"><a href="${ticket.attachment_path}" target="_blank" style="color:#0284c7; font-size:12px; display:inline-flex; align-items:center; gap:0.25rem;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Ver captura en tamaño completo</a></div>
                    `;
                } else {
                    attachmentContent.innerHTML = `
                        <a href="${ticket.attachment_path}" target="_blank" class="btn-secondary" style="font-size:12px; padding:0.4rem 0.8rem; display:inline-flex; align-items:center; gap:0.35rem;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Descargar Documento Adjunto (${fileExt.toUpperCase()})
                        </a>
                    `;
                }
            } else {
                attachmentContainer.style.display = 'none';
                attachmentContent.innerHTML = '';
            }

            // Control de Visibilidad del Botón "Eliminar Ticket" en el Modal
            const deleteBtn = document.getElementById('btn-delete-ticket-modal');
            if (deleteBtn) {
                const canDelete = (window.CURRENT_USER_ROLE === 'admin' 
                    || (window.CURRENT_USER_ROLE === 'manager' && parseInt(ticket.area_id) === parseInt(window.CURRENT_USER_AREA_ID))
                    || (parseInt(ticket.requester_id) === parseInt(window.CURRENT_USER_ID) && parseInt(ticket.status_id) === 1));
                deleteBtn.style.display = canDelete ? 'inline-flex' : 'none';
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
                        <span><strong>${escapeHtml(c.user_name)}</strong> (${c.role_name}) ${isInternal ? '<em style="color:#D97706; display:inline-flex; align-items:center; gap:3px;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Nota Privada Interna</em>' : ''}</span>
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
        success: `<div class="toast-icon-wrapper toast-icon-success"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>`,
        error: `<div class="toast-icon-wrapper toast-icon-error"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>`,
        warning: `<div class="toast-icon-wrapper toast-icon-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>`,
        info: `<div class="toast-icon-wrapper toast-icon-info"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>`
    };

    toast.className = `toast-${type}`;
    toast.innerHTML = `
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:0.75rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                ${icons[type] || icons.info}
                <span style="font-size:13px; font-weight:500; color:#1E293B; line-height:1.4;">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.closest('#toast-notification').style.display='none'" style="background:none; border:none; color:#94A3B8; font-size:16px; cursor:pointer; padding:0 2px; line-height:1; display:flex; align-items:center; justify-content:center; transition:color 0.15s ease;" onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94A3B8'" aria-label="Cerrar">&times;</button>
        </div>
    `;
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

// ==========================================================================
// SELECCIÓN Y APERTURA DE TICKET AL HACER CLIC EN LA FILA
// ==========================================================================
function initTicketRowClickSelection() {
    document.addEventListener('click', (e) => {
        // Ignorar si el clic fue en un elemento de acción directo (botones, enlaces, inputs, etc.)
        if (e.target.closest('button, a, input, select, textarea, label, .btn-primary, .btn-secondary, .fd-ticket-link')) {
            return;
        }

        // Buscar si el clic proviene de una fila de ticket
        const row = e.target.closest('tr.ticket-row, tr[data-ticket-id]');
        if (!row) return;

        const ticketId = row.dataset.ticketId;
        if (ticketId && typeof openTicketModal === 'function') {
            openTicketModal(parseInt(ticketId, 10));
        }
    });
}

function filterTicketsGlobal(query) {
    query = (query || '').toLowerCase().trim();

    if (typeof applyAllFilters === 'function') {
        const globalSearch = document.getElementById('filter-global-search');
        if (globalSearch && globalSearch.value !== query) {
            globalSearch.value = query;
        }
        applyAllFilters();
        return;
    }

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
            emptyRow.innerHTML = '<td colspan="10" style="text-align:center; padding:2rem; color:var(--fd-text-dim);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:6px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>No se encontraron tickets que coincidan con la búsqueda.</td>';
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
            emptyRow.innerHTML = '<td colspan="8" style="text-align:center; padding:2rem; color:var(--fd-text-dim);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:6px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>No se encontraron solicitudes que coincidan con la búsqueda.</td>';
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
            emptyRow.innerHTML = '<td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-dim);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:6px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>No se encontraron tickets pendientes que coincidan con la búsqueda.</td>';
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

function getNotifIconSvg(iconType) {
    switch (iconType) {
        case 'inbox':
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';
        case 'alert':
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        case 'star':
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="#F59E0B" stroke="#D97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        case 'comment':
        case 'message':
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        default:
            return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
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
            list.innerHTML = '<div class="fd-notif-empty">No tienes notificaciones pendientes.</div>';
            return;
        }

        list.innerHTML = notifications.map(n => `
            <div class="fd-notif-item unread" onclick="handleNotificationClick(${n.ticket_id})">
                <div class="fd-notif-icon">${getNotifIconSvg(n.icon)}</div>
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
            setTimeout(() => {
                window.location.href = 'index.php?tab=admin-tab';
            }, 1000);
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

async function saveModalSharePointChanges() {
    if (!currentTicketData || !currentTicketData.id) {
        showToast('No hay un ticket cargado actualmente.', 'error');
        return;
    }

    const ticketId = currentTicketData.id;
    const title = (document.getElementById('modal-sp-title-input')?.value || '').trim();
    const companyName = (document.getElementById('modal-sp-company-input')?.value || '').trim();
    const areaCode = (document.getElementById('modal-sp-area-select')?.value || 'IT').trim();
    const responsable = (document.getElementById('modal-sp-responsable-input')?.value || '').trim();
    const observations = (document.getElementById('modal-sp-observations-input')?.value || '').trim();
    const spStatus = (document.getElementById('modal-sp-status-select')?.value || 'Pendiente').trim();
    const priority = (document.getElementById('modal-prop-priority-select')?.value || 'medium').trim();

    if (!title) {
        showToast('El asunto de la solicitud no puede estar vacío.', 'error');
        return;
    }

    const btn = document.getElementById('btn-save-sharepoint-modal');
    const feedback = document.getElementById('modal-sp-save-feedback');
    const originalBtnText = btn ? btn.innerHTML : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Guardar Cambios';

    try {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="spin" style="vertical-align:-2px; margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>Guardando...';
        }
        if (feedback) {
            feedback.style.display = 'block';
            feedback.style.background = '#EFF6FF';
            feedback.style.color = '#1E40AF';
            feedback.textContent = 'Guardando cambios del ticket...';
        }

        const csrfToken = getCsrfToken();

        const payload = {
            ticket_id: ticketId,
            title: title,
            company_name: companyName,
            area_code: areaCode,
            responsable: responsable,
            observations: observations,
            sp_status: spStatus,
            priority: priority,
            csrf_token: csrfToken
        };

        const response = await fetch('api.php?action=update_sharepoint_ticket', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success && result.ticket) {
            const updated = result.ticket;
            currentTicketData = updated;

            // 1. Actualizar cabecera del modal
            const modalTitleEl = document.getElementById('modal-ticket-title');
            if (modalTitleEl) modalTitleEl.textContent = `${updated.code}: ${updated.title}`;

            const statusPill = document.getElementById('modal-ticket-status-pill');
            if (statusPill) {
                statusPill.textContent = updated.sp_status;
                let statusClass = 'fd-status-pendiente';
                const lowerStatus = (updated.sp_status || '').toLowerCase();
                if (lowerStatus.includes('procesado')) statusClass = 'fd-status-procesado';
                else if (lowerStatus.includes('en proceso') || lowerStatus.includes('en_proceso')) statusClass = 'fd-status-en-proceso';
                else if (lowerStatus.includes('stand by') || lowerStatus.includes('stand_by')) statusClass = 'fd-status-stand-by';
                statusPill.className = `fd-status-badge ${statusClass}`;
            }

            // Sincronizar selectores del modal con los datos guardados
            const propSelect = document.getElementById('modal-prop-status-select');
            if (propSelect) propSelect.value = updated.status_id;
            const spSelect = document.getElementById('modal-sp-status-select');
            if (spSelect) spSelect.value = updated.sp_status;

            // 2. Mover tarjeta en el Tablero Kanban
            const card = document.querySelector(`.ticket-card[data-ticket-id="${ticketId}"]`);
            const targetContainer = document.querySelector(`.tickets-container[data-status-id="${updated.status_id}"]`);
            if (card && targetContainer && card.parentElement !== targetContainer) {
                targetContainer.appendChild(card);
                if (typeof updateColumnCounters === 'function') updateColumnCounters();
            }

            // 3. Actualizar la fila en la tabla de Todos los tickets
            const row = document.querySelector(`#all-tickets-tbody tr[data-ticket-id="${ticketId}"]`);
            if (row) {
                row.dataset.statusId = updated.status_id;
                row.dataset.title = (updated.title || '').toLowerCase();
                row.dataset.company = (updated.company_name || '').toLowerCase();
                row.dataset.areaName = (updated.area_code || '').toLowerCase();
                row.dataset.responsable = (updated.responsable || 'sin asignar').toLowerCase();
                row.dataset.observations = (updated.observations || '').toLowerCase();
                row.dataset.spStatus = (updated.sp_status || '').toLowerCase();
                row.dataset.statusName = (updated.sp_status || '').toLowerCase();

                const link = row.querySelector('.fd-ticket-link');
                if (link) link.textContent = updated.title;

                const cells = row.querySelectorAll('td');
                if (cells.length >= 8) {
                    // Celda 2: Empresa
                    cells[1].innerHTML = `<div style="font-weight:600; color:#1E293B; font-size:13px;">${escapeHtml(updated.company_name || 'N/A')}</div>`;

                    // Celda 3: Área
                    const aCode = (updated.area_code || 'IT').toUpperCase();
                    const aClass = (aCode === 'SSGG') ? 'fd-area-ssgg' : 'fd-area-it';
                    const aIcon = (aCode === 'SSGG') 
                        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>' 
                        : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
                    cells[2].innerHTML = `<span class="fd-area-badge ${aClass}">${aIcon}${escapeHtml(aCode)}</span>`;

                    // Celda 4: Responsable
                    const respDisplay = updated.responsable ? escapeHtml(updated.responsable) : '<em style="color:#94a3b8">Sin Asignar</em>';
                    cells[3].innerHTML = `<span class="agent-name-display" style="font-weight:500; font-size:12px; color:#334155;">${respDisplay}</span>`;

                    // Celda 5: Observaciones
                    const obsDisplay = updated.observations ? escapeHtml(updated.observations) : '<span style="color:#CBD5E1;">—</span>';
                    cells[4].innerHTML = `<div class="fd-observations-cell" title="${escapeHtml(updated.observations || '')}">${obsDisplay}</div>`;

                    // Celda 6: Estatus
                    let stClass = 'fd-status-pendiente';
                    const stLower = (updated.sp_status || '').toLowerCase();
                    if (stLower.includes('procesado')) stClass = 'fd-status-procesado';
                    else if (stLower.includes('en proceso') || stLower.includes('en_proceso')) stClass = 'fd-status-en-proceso';
                    else if (stLower.includes('stand by') || stLower.includes('stand_by')) stClass = 'fd-status-stand-by';
                    cells[5].innerHTML = `<span class="fd-status-badge ${stClass}">${escapeHtml(updated.sp_status)}</span>`;

                    // Celda 9: Fecha Cierre (si está en la tabla de 10 columnas)
                    if (cells.length >= 10 && updated.resolved_at !== undefined) {
                        cells[8].innerHTML = updated.resolved_at ? escapeHtml(updated.resolved_at.substring(0, 16)) : '<span style="color:#CBD5E1;">—</span>';
                    }
                }
            }

            // Actualizar pill de prioridad si cambió
            const prioPill = document.getElementById('modal-ticket-prio-pill');
            if (prioPill && updated.priority) {
                prioPill.textContent = updated.priority;
                prioPill.className = `fd-prio-badge fd-prio-${updated.priority}`;
            }

            if (feedback) {
                feedback.style.display = 'block';
                feedback.style.background = '#ECFDF5';
                feedback.style.color = '#065F46';
                feedback.innerHTML = '<span style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>¡Cambios guardados exitosamente!</span>';
                setTimeout(() => {
                    feedback.style.display = 'none';
                }, 4000);
            }
            showToast('Ticket actualizado exitosamente.', 'success');
        } else {
            if (feedback) {
                feedback.style.display = 'block';
                feedback.style.background = '#FEF2F2';
                feedback.style.color = '#991B1B';
                feedback.innerHTML = '<span style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' + escapeHtml(result.message || 'Error al guardar los cambios.') + '</span>';
            }
            showToast(result.message || 'Error al actualizar el ticket.', 'error');
        }
    } catch (e) {
        if (feedback) {
            feedback.style.display = 'block';
            feedback.style.background = '#FEF2F2';
            feedback.style.color = '#991B1B';
            feedback.innerHTML = '<span style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>Error de red: ' + escapeHtml(e.message) + '</span>';
        }
        showToast('Error de conexión al guardar.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
        }
    }
}

async function exportCurrentTicketToSharePoint(ticketId) {
    if (!confirm('¿Desea crear este registro en la lista de producción de SharePoint y vincular su ID?')) {
        return;
    }
    const btn = document.getElementById('btn-export-to-sharepoint');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin" style="margin-right:4px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/></svg>Vinculando...';
    }

    try {
        const response = await fetch('api.php?action=export_ticket_to_sharepoint', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ ticket_id: ticketId, csrf_token: getCsrfToken() })
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            await openTicketModal(ticketId);
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) {
        showToast('Error de red al conectar con SharePoint.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

/**
 * Eliminar un ticket con confirmación interactiva y actualización reactiva de la interfaz
 */
async function deleteTicket(ticketId, ticketCode, sharepointId) {
    if (!ticketId) return;
    ticketCode = ticketCode || ('#' + ticketId);

    let deleteSharePoint = false;
    let confirmMsg = `¿Está seguro de que desea eliminar permanentemente el ticket ${ticketCode}?`;
    if (sharepointId && parseInt(sharepointId) > 0) {
        confirmMsg += `\n\nEste ticket está vinculado a SharePoint (ID #${sharepointId}).\n¿Desea solicitar también la eliminación del registro en SharePoint?`;
        deleteSharePoint = true;
    }
    confirmMsg += `\n\nEsta acción eliminará el ticket, sus comentarios y su historial de auditoría. No se puede revertir.`;

    if (!confirm(confirmMsg)) {
        return;
    }

    try {
        const response = await fetch('api.php?action=delete_ticket', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({
                ticket_id: parseInt(ticketId, 10),
                delete_sharepoint: deleteSharePoint,
                csrf_token: getCsrfToken()
            })
        });

        let result;
        try {
            result = await response.json();
        } catch (jsonErr) {
            console.error('Error parseando JSON de eliminación:', jsonErr);
            showToast('El ticket fue procesado en el servidor.', 'info');
            return;
        }

        if (result && result.success) {
            showToast(result.message || `Ticket ${ticketCode} eliminado con éxito.`, 'success');

            try {
                // Cerrar el modal si estaba abierto con este ticket
                const modal = document.getElementById('ticket-modal');
                const modalIdInput = document.getElementById('modal-ticket-id-input');
                if (modal && modalIdInput && parseInt(modalIdInput.value) === parseInt(ticketId, 10)) {
                    modal.style.display = 'none';
                }

                // Remover elementos del DOM en todas las tablas y en el kanban
                document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(el => el.remove());
                document.querySelectorAll(`.ticket-card[data-ticket-id="${ticketId}"]`).forEach(el => el.remove());

                // Actualizar tablas y paginación si las funciones están disponibles
                if (typeof filterTicketsGlobal === 'function') {
                    filterTicketsGlobal();
                }
                if (typeof filterMyRequests === 'function') {
                    filterMyRequests();
                }
            } catch (uiErr) {
                console.warn('Actualización de DOM tras eliminar:', uiErr);
            }
        } else {
            showToast(result ? result.message : 'Error al eliminar el ticket.', 'error');
        }
    } catch (e) {
        console.error('Error en deleteTicket:', e);
        showToast('Error de conexión al intentar eliminar el ticket.', 'error');
    }
}


function deleteCurrentModalTicket() {
    if (!currentTicketData || !currentTicketData.id) return;
    deleteTicket(currentTicketData.id, currentTicketData.code, currentTicketData.sharepoint_id);
}

window.syncModalStatusFromId = syncModalStatusFromId;
window.syncModalStatusFromName = syncModalStatusFromName;
window.handleDetailStatusChange = handleDetailStatusChange;
window.openCsatModal = openCsatModal;
window.closeCsatModal = closeCsatModal;
window.selectCsatRating = selectCsatRating;
window.pickupTicket = pickupTicket;
window.openAssignAgentDialog = openAssignAgentDialog;
window.openTicketModal = openTicketModal;
window.saveModalSharePointChanges = saveModalSharePointChanges;
window.exportCurrentTicketToSharePoint = exportCurrentTicketToSharePoint;
window.deleteTicket = deleteTicket;
window.deleteCurrentModalTicket = deleteCurrentModalTicket;
window.loadUnassignedTickets = loadUnassignedTickets;
window.loadNotifications = loadNotifications;
window.toggleNotificationsDropdown = toggleNotificationsDropdown;
window.handleNotificationClick = handleNotificationClick;
window.toggleUserRole = toggleUserRole;
window.filterTicketsGlobal = filterTicketsGlobal;
window.filterMyRequests = filterMyRequests;
window.filterInboxTickets = filterInboxTickets;
window.showToast = showToast;
window.initTicketRowClickSelection = initTicketRowClickSelection;
window.switchAdminSection = switchAdminSection;
window.filterUsersTable = filterUsersTable;
window.openCreateUserModal = openCreateUserModal;
window.closeCreateUserModal = closeCreateUserModal;
window.openEditUserModal = openEditUserModal;
window.closeEditUserModal = closeEditUserModal;
window.openResetPasswordModal = openResetPasswordModal;
window.closeResetPasswordModal = closeResetPasswordModal;
window.toggleUserStatus = toggleUserStatus;
window.deleteUser = deleteUser;



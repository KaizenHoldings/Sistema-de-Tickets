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

                <!-- 2.7. TICKETS PENDIENTES & HOJA DE TAREAS OPERATIVA -->
                <button class="fd-nav-item nav-tab <?= ($activeTab === 'pending-tab') ? 'active' : '' ?>" data-target="pending-tab" data-tooltip="Tickets Pendientes y Tareas" style="position: relative;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <span>Pendientes</span>
                    <span id="pending-badge-count" style="background: #F59E0B; color: white; border-radius: 50%; padding: 0.1rem 0.4rem; font-size: 10px; font-weight: bold; position: absolute; top: 0.25rem; right: 0.25rem; box-shadow: 0 0 5px rgba(245,158,11,0.5); <?= ($pendingCountTotal > 0) ? '' : 'display:none;' ?>">
                        <?= $pendingCountTotal ?>
                    </span>
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

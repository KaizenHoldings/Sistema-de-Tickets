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
                            <option value="0" <?= ($areaFilter == 0) ? 'selected' : '' ?>>Ver Todas las Áreas</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= $area['id'] ?>" <?= ($areaFilter == $area['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($area['name']) ?>
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
                            <h4>Notificaciones</h4>
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

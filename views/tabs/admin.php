<?php if ($currentUser['role_name'] === 'admin'): ?>
            <section id="admin-tab" class="tab-content <?= ($activeTab === 'admin-tab') ? 'active' : '' ?>">
                <!-- CABECERA DE ADMINISTRACIÓN -->
                <div class="fd-view-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
                    <div class="fd-view-title">
                        <h1>Administración y Manejo de Usuarios</h1>
                        <p>Gestión integral de colaboradores, roles de acceso, credenciales y departamentos</p>
                    </div>
                    <div class="fd-superadmin-env-pill">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <span>Superadmin (.env): <strong><?= htmlspecialchars(\Config\Env::get('SUPERADMIN_EMAIL', 'superadmin@empresa.com')) ?></strong></span>
                    </div>
                </div>

                <!-- TARJETAS DE MÉTRICAS RÁPIDAS (KPI GRID EN 4 COLUMNAS) -->
                <div class="fd-admin-kpi-grid">
                    <div class="fd-admin-kpi-card kpi-total">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Total Usuarios</span>
                            <div class="fd-admin-kpi-icon icon-blue">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number"><?= count($users) ?></div>
                        <div class="fd-admin-kpi-subtext">Colaboradores registrados</div>
                    </div>

                    <div class="fd-admin-kpi-card kpi-active">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Usuarios Activos</span>
                            <div class="fd-admin-kpi-icon icon-green">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number" style="color:#059669;"><?= count(array_filter($users, fn($u) => (int)($u['is_active'] ?? 1) === 1)) ?></div>
                        <div class="fd-admin-kpi-subtext">Habilitados para operar</div>
                    </div>

                    <div class="fd-admin-kpi-card kpi-managers">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Coordinadores / Admins</span>
                            <div class="fd-admin-kpi-icon icon-amber">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number"><?= count(array_filter($users, fn($u) => in_array((int)$u['role_id'], [1, 2]))) ?></div>
                        <div class="fd-admin-kpi-subtext">Gestores de área y sistema</div>
                    </div>

                    <div class="fd-admin-kpi-card kpi-areas">
                        <div class="fd-admin-kpi-card-top">
                            <span class="fd-admin-kpi-label">Departamentos</span>
                            <div class="fd-admin-kpi-icon icon-purple">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                            </div>
                        </div>
                        <div class="fd-admin-kpi-number"><?= count($areas) ?></div>
                        <div class="fd-admin-kpi-subtext">Áreas operativas activas</div>
                    </div>
                </div>

                <!-- SELECTOR DE SUB-SECCIONES (PESTAÑAS SEGMENTADAS) -->
                <div class="fd-admin-nav-tabs">
                    <button type="button" class="fd-admin-nav-btn active" id="tab-btn-users" onclick="switchAdminSection('users')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Gestión de Usuarios (<?= count($users) ?>)</span>
                    </button>
                    <button type="button" class="fd-admin-nav-btn" id="tab-btn-areas" onclick="switchAdminSection('areas')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        <span>Departamentos y Áreas (<?= count($areas) ?>)</span>
                    </button>
                </div>

                <!-- ==========================================
                     SECCIÓN 1: GESTIÓN DE USUARIOS
                     ========================================== -->
                <div id="admin-users-view">
                    <!-- BARRA DE BÚSQUEDA Y FILTROS -->
                    <div class="fd-user-filter-toolbar">
                        <div class="fd-user-filter-inputs">
                            <!-- Búsqueda en vivo -->
                            <div class="fd-user-search-wrap">
                                <svg class="fd-user-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="user-search-input" placeholder="Buscar por nombre o correo..." oninput="filterUsersTable()">
                            </div>

                            <!-- Filtro Departamento -->
                            <select id="user-filter-area" class="fd-user-select" onchange="filterUsersTable()">
                                <option value="">Todos los Departamentos</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="Sin Área">Sin Área Asignada</option>
                            </select>

                            <!-- Filtro Rol -->
                            <select id="user-filter-role" class="fd-user-select" onchange="filterUsersTable()">
                                <option value="">Todos los Roles</option>
                                <option value="admin">Superadministrador</option>
                                <option value="manager">Coordinador</option>
                                <option value="colaborador">Colaborador</option>
                            </select>

                            <!-- Filtro Estado -->
                            <select id="user-filter-status" class="fd-user-select" onchange="filterUsersTable()">
                                <option value="">Todos los Estados</option>
                                <option value="1">Activos</option>
                                <option value="0">Inactivos</option>
                            </select>
                        </div>

                        <!-- Botón Nuevo Usuario -->
                        <button type="button" class="fd-btn-create-user" onclick="openCreateUserModal()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Nuevo Usuario</span>
                        </button>
                    </div>

                    <!-- TABLA PRINCIPAL DE USUARIOS -->
                    <div class="fd-table-card">
                        <table class="fd-table" id="users-management-table">
                            <thead>
                                <tr>
                                    <th>Usuario / Colaborador</th>
                                    <th>Departamento</th>
                                    <th>Rol Asignado</th>
                                    <th>Estado</th>
                                    <th>Actividad Tickets</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:2rem; color:var(--fd-text-dim);">No hay usuarios registrados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr class="user-row" 
                                            data-user-id="<?= $u['id'] ?>"
                                            data-name="<?= htmlspecialchars(strtolower($u['name'])) ?>"
                                            data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>"
                                            data-area="<?= htmlspecialchars($u['area_name'] ?? 'Sin Área') ?>"
                                            data-role="<?= htmlspecialchars($u['role_name']) ?>"
                                            data-active="<?= (int)($u['is_active'] ?? 1) ?>">
                                            <td>
                                                <div class="fd-user-cell">
                                                    <div class="fd-avatar-sm" style="background:<?= ((int)$u['role_id'] === 1) ? '#D97706' : (((int)$u['role_id'] === 2) ? '#2563EB' : '#059669') ?>;">
                                                        <?= strtoupper(substr($u['name'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fd-user-name-line">
                                                            <span class="fd-user-name-text"><?= htmlspecialchars($u['name']) ?></span>
                                                            <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                                                <span class="fd-user-you-badge">Tú</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="fd-user-email-text"><?= htmlspecialchars($u['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-size:12.5px; font-weight:500; color:#334155;">
                                                    <?= htmlspecialchars($u['area_name'] ?? 'Sin Área Asignada') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="user-role-badge user-role-<?= htmlspecialchars($u['role_name']) ?>">
                                                    <?= htmlspecialchars($roleDisplayNames[$u['role_name']] ?? ucfirst($u['role_name'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $isActive = ((int)($u['is_active'] ?? 1) === 1); ?>
                                                <span class="fd-user-status-pill <?= $isActive ? 'status-active' : 'status-inactive' ?>"
                                                      onclick="<?= ((int)$u['id'] !== (int)$currentUser['id']) ? "toggleUserStatus({$u['id']}, '" . htmlspecialchars(addslashes($u['name'])) . "', " . ($isActive ? 1 : 0) . ")" : "showToast('No puedes desactivar tu propia cuenta activa', 'error')" ?>"
                                                      title="<?= ((int)$u['id'] !== (int)$currentUser['id']) ? 'Click para alternar estado' : 'Sesión activa' ?>">
                                                    <span class="fd-user-status-dot"></span>
                                                    <span><?= $isActive ? 'Activo' : 'Inactivo' ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="user-activity-badge" title="Tickets creados / Asignados">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                    <span><?= (int)($u['created_tickets_count'] ?? 0) ?> creados / <strong><?= (int)($u['assigned_tickets_count'] ?? 0) ?> asignados</strong></span>
                                                </div>
                                            </td>
                                            <td style="text-align:right;">
                                                <div class="fd-user-actions-group">
                                                    <!-- Editar Usuario -->
                                                    <button type="button" class="fd-user-action-btn btn-edit" title="Editar datos del usuario" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                                                        'id' => (int)$u['id'],
                                                        'name' => $u['name'],
                                                        'email' => $u['email'],
                                                        'role_id' => (int)$u['role_id'],
                                                        'area_id' => $u['area_id'] ? (int)$u['area_id'] : '',
                                                        'is_active' => (int)($u['is_active'] ?? 1)
                                                    ])) ?>)">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>

                                                    <!-- Cambiar Contraseña -->
                                                    <button type="button" class="fd-user-action-btn btn-key" title="Restablecer contraseña" onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                    </button>

                                                    <!-- Activar / Desactivar -->
                                                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                                        <button type="button" class="fd-user-action-btn btn-power" title="<?= $isActive ? 'Desactivar usuario' : 'Activar usuario' ?>" onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', <?= $isActive ? 1 : 0 ?>)">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                                                        </button>

                                                        <!-- Eliminar Usuario -->
                                                        <button type="button" class="fd-user-action-btn btn-delete" title="Eliminar usuario" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
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
                </div>

                <!-- ==========================================
                     SECCIÓN 2: DEPARTAMENTOS Y ÁREAS
                     ========================================== -->
                <div id="admin-areas-view" style="display:none;">
                    <div class="fd-widgets-row" style="grid-template-columns: 1fr 1fr; margin-bottom:1.5rem;">
                        <!-- CREAR NUEVA ÁREA -->
                        <div class="fd-widget-card">
                            <div class="fd-widget-header">
                                <h3>Crear Nuevo Departamento / Área</h3>
                            </div>
                            <form id="create-area-form" style="display:flex; flex-direction:column; gap:1rem;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Nombre del Departamento:</label>
                                    <input type="text" name="name" class="form-control" placeholder="Ej: Seguridad y Auditoría" required>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Descripción del Departamento:</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Responsabilidades operativas del área..."></textarea>
                                </div>
                                <button type="submit" class="btn-primary" style="align-self:flex-start;">
                                    Guardar Departamento
                                </button>
                            </form>
                        </div>

                        <!-- RESUMEN DE COBERTURA -->
                        <div class="fd-widget-card" style="background:#F8FAFC;">
                            <div class="fd-widget-header">
                                <h3>Estructura Organizacional</h3>
                            </div>
                            <p style="font-size:13px; color:#64748B; margin-bottom:1rem; line-height:1.5;">
                                Los departamentos permiten segmentar las solicitudes entre <strong>Servicios Generales (SSGG)</strong>, <strong>Tecnología e Informática (IT)</strong> y demás unidades de soporte empresarial.
                            </p>
                            <div style="display:flex; flex-direction:column; gap:0.5rem; font-size:12px; color:#475569;">
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #E2E8F0;">
                                    <span>Áreas Registradas:</span>
                                    <strong><?= count($areas) ?></strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #E2E8F0;">
                                    <span>Usuarios Asignados a un Área:</span>
                                    <strong><?= count(array_filter($users, fn($u) => !empty($u['area_id']))) ?></strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0;">
                                    <span>Usuarios Sin Área (Acceso Global):</span>
                                    <strong><?= count(array_filter($users, fn($u) => empty($u['area_id']))) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LISTADO DE DEPARTAMENTOS -->
                    <div class="fd-table-card">
                        <div class="fd-table-header">
                            <h3>Departamentos Registrados en el Sistema</h3>
                            <span style="font-size:12px; color:var(--fd-text-dim);">Áreas vinculadas a solicitudes de tickets</span>
                        </div>
                        <table class="fd-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre del Departamento</th>
                                    <th>Descripción</th>
                                    <th>Coordinador / Responsable</th>
                                    <th>Colaboradores</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($areas as $a): ?>
                                    <?php 
                                        $areaUsersCount = count(array_filter($users, fn($u) => (int)($u['area_id'] ?? 0) === (int)$a['id']));
                                    ?>
                                    <tr>
                                        <td><span class="ticket-code">#<?= $a['id'] ?></span></td>
                                        <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                                        <td><span style="font-size:12px; color:#64748B;"><?= htmlspecialchars($a['description'] ?? 'Sin descripción') ?></span></td>
                                        <td>
                                            <?php if (!empty($a['manager_name'])): ?>
                                                <span style="color:#2563EB; font-weight:600; font-size:12px;"><?= htmlspecialchars($a['manager_name']) ?></span>
                                            <?php else: ?>
                                                <span style="color:#94A3B8; font-size:12px; font-style:italic;">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="user-activity-badge"><?= $areaUsersCount ?> miembros</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

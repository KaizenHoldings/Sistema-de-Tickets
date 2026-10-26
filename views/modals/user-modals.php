<div id="create-user-modal" class="modal-backdrop" style="z-index: 1200;">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Dar de Alta Usuario / Colaborador</h3>
            <button class="close-modal" onclick="closeCreateUserModal()">&times;</button>
        </div>
        <form id="modal-create-user-form" style="display:flex; flex-direction:column; gap:1rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Nombre Completo: <span style="color:#DC2626;">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Ej: Valentina Morales" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Correo Electrónico: <span style="color:#DC2626;">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="valentina@empresa.com" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Departamento / Área:</label>
                    <select name="area_id" class="form-control">
                        <option value="">-- Sin Área (Global) --</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Rol de Acceso: <span style="color:#DC2626;">*</span></label>
                    <select name="role_id" class="form-control" required>
                        <option value="3">Colaborador / Agente</option>
                        <option value="2">Coordinador de Área</option>
                        <option value="1">Superadministrador</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px; font-weight:600; color:#1E293B;">Contraseña Inicial:</label>
                <input type="password" name="password" class="form-control" value="password123" required>
                <small style="color:#64748B; font-size:11px;">Mínimo 6 caracteres. Por defecto: password123</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:0.75rem;">
                <button type="button" class="btn-secondary" onclick="closeCreateUserModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Registrar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL EDITAR USUARIO / COLABORADOR
     ==================================================================== -->
<div id="edit-user-modal" class="modal-backdrop" style="z-index: 1200;">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Editar Datos de Usuario</h3>
            <button class="close-modal" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form id="modal-edit-user-form" style="display:flex; flex-direction:column; gap:1rem;">
            <input type="hidden" name="user_id" id="edit-user-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Nombre Completo: <span style="color:#DC2626;">*</span></label>
                    <input type="text" name="name" id="edit-user-name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Correo Electrónico: <span style="color:#DC2626;">*</span></label>
                    <input type="email" name="email" id="edit-user-email" class="form-control" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Departamento / Área:</label>
                    <select name="area_id" id="edit-user-area-id" class="form-control">
                        <option value="">-- Sin Área (Global) --</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px; font-weight:600; color:#1E293B;">Rol de Acceso: <span style="color:#DC2626;">*</span></label>
                    <select name="role_id" id="edit-user-role-id" class="form-control" required>
                        <option value="3">Colaborador / Agente</option>
                        <option value="2">Coordinador de Área</option>
                        <option value="1">Superadministrador</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px; font-weight:600; color:#1E293B;">Nueva Contraseña (Opcional):</label>
                <input type="password" name="password" id="edit-user-password" class="form-control" placeholder="Dejar en blanco para conservar la actual">
                <small style="color:#64748B; font-size:11px;">Solo complete este campo si desea cambiar la contraseña del usuario.</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:0.75rem;">
                <button type="button" class="btn-secondary" onclick="closeEditUserModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL RESTABLECER CONTRASEÑA
     ==================================================================== -->
<div id="reset-password-modal" class="modal-backdrop" style="z-index: 1210;">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <h3>Restablecer Contraseña</h3>
            <button class="close-modal" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <form id="modal-reset-password-form" style="display:flex; flex-direction:column; gap:1rem;">
            <input type="hidden" name="user_id" id="reset-pwd-user-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <p style="font-size:13px; color:#475569; margin:0;">
                Asignar una nueva clave de acceso para: <strong id="reset-pwd-user-name" style="color:#0F172A;">-</strong>
            </p>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px; font-weight:600; color:#1E293B;">Nueva Contraseña: <span style="color:#DC2626;">*</span></label>
                <input type="password" name="new_password" id="reset-new-password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px; font-weight:600; color:#1E293B;">Confirmar Contraseña: <span style="color:#DC2626;">*</span></label>
                <input type="password" id="reset-confirm-password" class="form-control" placeholder="Repita la nueva contraseña" required minlength="6">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:0.5rem;">
                <button type="button" class="btn-secondary" onclick="closeResetPasswordModal()">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#D97706; border-color:#D97706;">Actualizar Contraseña</button>
            </div>
        </form>
    </div>
</div>

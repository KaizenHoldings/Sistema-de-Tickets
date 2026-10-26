<div id="create-ticket-modal" class="modal-backdrop">
    <div class="modal-content" style="max-width: 680px;">
        <div class="modal-header">
            <h3>Generar Nueva Solicitud de Servicio</h3>
            <button class="close-modal" onclick="closeCreateTicketModal()">&times;</button>
        </div>

        <form id="create-ticket-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- 1. SOLICITUD (ASUNTO) -->
            <div class="form-group">
                <label for="create_title" style="font-weight:600; color:#1E293B;">Solicitud / Asunto: <span style="color:#DC2626;">*</span></label>
                <input type="text" name="title" id="create_title" class="form-control" placeholder="Ej: Falla de red en piso 3 o Mantenimiento de aire acondicionado" required>
            </div>

            <!-- FILA 1: EMPRESA Y ÁREA -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- 2. EMPRESA -->
                <div class="form-group">
                    <label for="create_company_name" style="font-weight:600; color:#1E293B;">Empresa: <span style="color:#DC2626;">*</span></label>
                    <input type="text" name="company_name" id="create_company_name" list="create-companies-datalist" class="form-control" placeholder="Seleccione o escriba la empresa..." value="Kaizen Holdings" required>
                    <datalist id="create-companies-datalist">
                        <?php foreach ($uniqueCompanies as $comp): ?>
                            <option value="<?= htmlspecialchars($comp) ?>"><?= htmlspecialchars($comp) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- 3. ÁREA (IT / SSGG) -->
                <div class="form-group">
                    <label for="create_area_code" style="font-weight:600; color:#1E293B;">Área: <span style="color:#DC2626;">*</span></label>
                    <select name="area_code" id="create_area_code" class="form-control" required>
                        <option value="IT" selected>IT (Tecnología e Informática)</option>
                        <option value="SSGG">SSGG (Servicios Generales y Mantenimiento)</option>
                    </select>
                </div>
            </div>

            <!-- FILA 2: RESPONSABLE Y ESTATUS -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- 4. RESPONSABLE -->
                <div class="form-group">
                    <label for="create_responsable" style="font-weight:600; color:#1E293B;">Responsable (Opcional):</label>
                    <input type="text" name="responsable" id="create_responsable" list="create-responsables-datalist" class="form-control" placeholder="Sin Asignar...">
                    <datalist id="create-responsables-datalist">
                        <?php foreach ($uniqueResponsables as $resp): ?>
                            <option value="<?= htmlspecialchars($resp) ?>"><?= htmlspecialchars($resp) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- 5. ESTATUS -->
                <div class="form-group">
                    <label for="create_sp_status" style="font-weight:600; color:#1E293B;">Estatus Inicial:</label>
                    <select name="sp_status" id="create_sp_status" class="form-control">
                        <option value="Pendiente" selected>Pendiente</option>
                        <option value="En proceso">En proceso</option>
                        <option value="Stand By">Stand By</option>
                        <option value="Procesado">Procesado</option>
                    </select>
                </div>
            </div>

            <!-- FILA 3: PRIORIDAD Y FECHA PROPUESTA -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- 6. PRIORIDAD -->
                <div class="form-group">
                    <label for="create_priority" style="font-weight:600; color:#1E293B;">Prioridad:</label>
                    <select name="priority" id="create_priority" class="form-control">
                        <option value="low">Baja (Requerimiento de rutina)</option>
                        <option value="medium" selected>Media (Impacto regular)</option>
                        <option value="high">Alta (Impacto importante)</option>
                        <option value="urgent">Urgente (Bloqueo crítico)</option>
                    </select>
                </div>

                <!-- 7. FECHA PROPUESTA -->
                <div class="form-group">
                    <label for="create_due_date" style="font-weight:600; color:#1E293B;">Fecha Propuesta (Límite):</label>
                    <input type="datetime-local" name="due_date" id="create_due_date" class="form-control">
                </div>
            </div>

            <!-- 8. OBSERVACIONES -->
            <div class="form-group">
                <label for="create_observations" style="font-weight:600; color:#1E293B;">Observaciones / Detalle:</label>
                <textarea name="observations" id="create_observations" class="form-control" rows="3" placeholder="Detalle del requerimiento, observaciones o síntomas del problema presentado..."></textarea>
            </div>

            <!-- 9. ADJUNTO -->
            <div class="form-group" style="background:#F8FAFC; border:1px dashed #CBD5E1; padding:0.75rem 0.85rem; border-radius:6px;">
                <label for="create_attachment" style="font-weight:600; font-size:12px; margin-bottom:4px; display:block; color:#475569;">Adjuntar Evidencia / Captura (Opcional):</label>
                <input type="file" name="attachment" id="create_attachment" class="form-control" accept="image/*,.pdf" style="padding:0.35rem; background:white; font-size:12px;">
            </div>

            <!-- 10. OPCIÓN PARA CREAR/SINCRONIZAR EN SHAREPOINT -->
            <div class="form-group" style="background:#F0F9FF; border:1px solid #BAE6FD; padding:0.75rem 0.85rem; border-radius:6px; margin-top:0.5rem;">
                <label style="display:flex; align-items:flex-start; gap:0.6rem; cursor:pointer; margin-bottom:0;">
                    <input type="checkbox" name="sync_sharepoint" id="create-sync-sharepoint" value="1" style="width:17px; height:17px; margin-top:2px; accent-color:#0284C7;">
                    <div>
                        <span style="font-weight:600; color:#0369A1; font-size:13px; display:flex; align-items:center; gap:5px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284C7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/><polyline points="12 14 16 10 12 6"/><line x1="16" y1="10" x2="6" y2="10"/></svg>
                            Crear y sincronizar también en SharePoint (Producción)
                        </span>
                        <p style="font-size:11px; color:#0284C7; margin:2px 0 0 0;">
                            Crea automáticamente el registro correspondiente en la lista de SharePoint (Microsoft Graph) y le asigna su código correlativo oficial.
                        </p>
                    </div>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn-secondary" onclick="closeCreateTicketModal()">Cancelar</button>
                <button type="submit" class="btn-primary" style="padding:0.5rem 1.25rem; font-weight:600;">
                    Guardar Solicitud
                </button>
            </div>
        </form>
    </div>
</div>

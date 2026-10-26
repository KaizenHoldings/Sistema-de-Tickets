<div id="ticket-modal" class="modal-backdrop">
    <div class="fd-modal-ticket">
        <!-- CABECERA DE TICKET -->
        <div class="fd-modal-top">
            <div class="fd-modal-title-group">
                <h2 id="modal-ticket-title">Detalle del Ticket</h2>
                <span id="modal-ticket-status-pill" class="fd-status-badge">Estado</span>
                <span id="modal-ticket-prio-pill" class="fd-prio-badge">Prioridad</span>
            </div>
            <button class="close-modal" onclick="closeTicketModal()">&times;</button>
        </div>

        <!-- CONTENEDOR EN UNA SOLA COLUMNA CON PROPIEDADES ARRIBA -->
        <div class="fd-modal-body-single">
            <!-- SECCIÓN SUPERIOR: PROPIEDADES UNIFICADAS DEL TICKET -->
            <div class="fd-modal-properties-top">
                <div class="fd-properties-header">
                    <div style="display:flex; align-items:center; gap:0.6rem;">
                        <span style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#1E293B; display:flex; align-items:center; gap:0.4rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284C7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:4px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Propiedades del Ticket
                        </span>
                        <span id="modal-sp-id-tag" style="font-size:11px; padding:2px 8px; border-radius:4px; background:#E0F2FE; color:#0369A1; font-weight:700;">
                            ID: -
                        </span>
                        <button type="button" id="btn-export-to-sharepoint" class="btn-secondary" style="display:none; font-size:11px; padding:0.25rem 0.6rem; background:#F0F9FF; color:#0369A1; border:1px solid #BAE6FD; align-items:center; gap:4px; cursor:pointer;" title="Crear este ticket en la lista de SharePoint">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/><polyline points="12 14 16 10 12 6"/><line x1="16" y1="10" x2="6" y2="10"/></svg>Vincular a SharePoint
                        </button>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        <div style="background:#ECFDF5; padding:0.3rem 0.65rem; border-radius:6px; font-size:11px; border:1px solid #A7F3D0;">
                            <span style="color:#059669; font-weight:700;">● SLA: Dentro del Tiempo</span>
                        </div>
                        <div id="modal-due-date-pill" style="background:#F8FAFC; padding:0.3rem 0.65rem; border-radius:6px; font-size:11px; border:1px solid #CBD5E1; color:#475569;">
                            Propuesta: <strong id="modal-ticket-due-date" style="color:#1E293B;">—</strong>
                        </div>
                        <div id="modal-resolved-date-pill" style="background:#F0FDF4; padding:0.3rem 0.65rem; border-radius:6px; font-size:11px; border:1px solid #BBF7D0; color:#166534; display:none;">
                            Cierre: <strong id="modal-ticket-resolved-date" style="color:#14532D;">—</strong>
                        </div>
                    </div>
                </div>

                <!-- Input auxiliar para mantener compatibilidad JS -->
                <input type="hidden" id="modal-prop-status-select" value="1">
                <span id="modal-ticket-agent" style="display:none;">-</span>

                <!-- GRID DE PROPIEDADES UNIFICADAS -->
                <div class="fd-properties-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <!-- 1. Estado / Estatus -->
                    <div class="fd-prop-group">
                        <label for="modal-sp-status-select">Estado:</label>
                        <select id="modal-sp-status-select" class="fd-prop-select" onchange="syncModalStatusFromName(this.value)">
                            <option value="Pendiente">Pendiente</option>
                            <option value="En proceso">En proceso</option>
                            <option value="Stand By">Stand By</option>
                            <option value="Procesado">Procesado</option>
                        </select>
                    </div>

                    <!-- 2. Prioridad -->
                    <div class="fd-prop-group">
                        <label for="modal-prop-priority-select">Prioridad:</label>
                        <select id="modal-prop-priority-select" class="fd-prop-select">
                            <option value="low">Baja</option>
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>

                    <!-- 3. Responsable / Agente Asignado -->
                    <div class="fd-prop-group">
                        <label for="modal-sp-responsable-input">Responsable:</label>
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <input type="text" id="modal-sp-responsable-input" list="modal-sp-responsables-datalist" class="fd-filter-input" placeholder="Nombre o iniciales..." style="width:100%; min-height:36px; padding:0.45rem 0.6rem; font-size:13px; border-radius:6px; border:1px solid #CBD5E1;">
                            <datalist id="modal-sp-responsables-datalist">
                                <?php foreach ($uniqueResponsables as $resp): ?>
                                    <option value="<?= htmlspecialchars($resp) ?>"><?= htmlspecialchars($resp) ?></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (in_array($currentUser['role_name'], ['admin', 'manager', 'colaborador', 'agent'])): ?>
                            <div id="modal-assign-btn-container">
                                <button type="button" class="btn-secondary" id="modal-assign-trigger-btn" style="white-space:nowrap; font-size:11px; padding:0.45rem 0.6rem; height:36px; display:inline-flex; align-items:center; gap:3px;" title="Seleccionar agente">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Agentes
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 4. Empresa / Solicitante -->
                    <div class="fd-prop-group">
                        <label for="modal-sp-company-input">Empresa:</label>
                        <input type="text" id="modal-sp-company-input" list="modal-sp-companies-datalist" class="fd-filter-input" placeholder="Nombre de la empresa..." style="width:100%; min-height:36px; padding:0.45rem 0.6rem; font-size:13px; border-radius:6px; border:1px solid #CBD5E1;">
                        <datalist id="modal-sp-companies-datalist">
                            <?php foreach ($uniqueCompanies as $comp): ?>
                                <option value="<?= htmlspecialchars($comp) ?>"><?= htmlspecialchars($comp) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <!-- 5. Área -->
                    <div class="fd-prop-group">
                        <label for="modal-sp-area-select">Área:</label>
                        <select id="modal-sp-area-select" class="fd-prop-select" style="min-height:36px;">
                            <option value="IT">IT (Tecnología e Informática)</option>
                            <option value="SSGG">SSGG (Servicios Generales)</option>
                        </select>
                    </div>
                </div>

                <!-- FILA DE ASUNTO, OBSERVACIONES Y ACCIÓN DE GUARDADO -->
                <div style="margin-top:0.85rem; padding-top:0.85rem; border-top:1px dashed #CBD5E1; display:flex; flex-direction:column; gap:0.75rem;">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:0.85rem;">
                        <div class="fd-prop-group" style="margin-bottom:0;">
                            <label for="modal-sp-title-input">Asunto de la Solicitud:</label>
                            <input type="text" id="modal-sp-title-input" class="fd-filter-input" placeholder="Título o asunto de la solicitud..." style="width:100%; padding:0.45rem 0.6rem; font-size:13px; border-radius:6px; border:1px solid #CBD5E1;">
                        </div>
                        <div class="fd-prop-group" style="margin-bottom:0;">
                            <label for="modal-sp-observations-input">Observaciones:</label>
                            <textarea id="modal-sp-observations-input" rows="1" class="fd-filter-input" placeholder="Escriba observaciones de la solicitud..." style="width:100%; padding:0.45rem 0.6rem; font-size:12px; border-radius:6px; border:1px solid #CBD5E1; resize:vertical; font-family:inherit;"></textarea>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-top:0.25rem;">
                        <button type="button" id="btn-delete-ticket-modal" class="btn-danger" style="display:none; padding:0.45rem 0.9rem; font-size:12px; background:#EF4444; color:#FFFFFF; border:none; border-radius:6px; font-weight:600; cursor:pointer; align-items:center; gap:5px; transition:background 0.2s;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'" onclick="deleteCurrentModalTicket()">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            Eliminar Ticket
                        </button>
                        <div style="display:flex; align-items:center; gap:0.75rem; margin-left:auto;">
                            <div id="modal-sp-save-feedback" style="display:none; font-size:12px; padding:0.35rem 0.75rem; border-radius:6px; font-weight:600;"></div>
                            <button type="button" id="btn-save-sharepoint-modal" class="btn-primary" style="padding:0.5rem 1.25rem; font-size:13px; background:#0284C7; font-weight:600; box-shadow:0 1px 3px rgba(2,132,199,0.3); display:inline-flex; align-items:center; gap:6px;" onclick="saveModalSharePointChanges()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Guardar Cambios
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- SECCIÓN INFERIOR: REQUERIMIENTO ORIGINAL, HISTORIAL Y NOTAS -->
            <div class="fd-modal-content-bottom">
                <input type="hidden" id="modal-ticket-id-input">

                <!-- BANNER CSAT (ENCUESTA DE SATISFACCIÓN) -->
                <div id="modal-csat-banner" style="display:none; margin-bottom:1rem;"></div>

                <!-- TARJETA DE REQUERIMIENTO ORIGINAL -->
                <div class="fd-initial-request-box">
                    <div class="fd-request-author">
                        <div class="fd-user-cell">
                            <div class="fd-avatar-sm" id="modal-author-avatar">U</div>
                            <div>
                                <strong id="modal-ticket-requester">-</strong>
                                <span style="color:var(--fd-text-dim); font-size:12px; margin-left:6px;" id="modal-ticket-date"></span>
                            </div>
                        </div>
                        <div style="font-size:12px; color:var(--fd-text-muted);">
                            Departamento: <strong id="modal-ticket-area" style="color:var(--fd-text-title);">-</strong>
                        </div>
                    </div>
                    <p id="modal-ticket-description" class="fd-request-desc"></p>

                    <!-- CONTENEDOR DE ARCHIVO ADJUNTO -->
                    <div id="modal-attachment-container" style="display:none; margin-top:1rem; padding-top:0.75rem; border-top:1px dashed #CBD5E1;">
                        <h4 style="font-size:12px; color:#2563EB; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.35rem;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>Evidencia Adjunta:</h4>
                        <div id="modal-attachment-content"></div>
                    </div>
                </div>

                <!-- LÍNEA DE TIEMPO DE COMENTARIOS / NOTAS PRIVADAS -->
                <div>
                    <h3 style="font-size:13px; font-weight:700; color:var(--fd-text-title); margin-bottom:0.75rem;">
                        Historial de Conversación & Notas Internas
                    </h3>
                    <div id="comments-container" class="comments-timeline"></div>
                </div>

                <!-- CAJA DE RESPUESTA / NOTA PRIVADA -->
                <div class="fd-reply-box">
                    <div class="fd-reply-tabs">
                        <button type="button" id="tab-reply-public" class="fd-reply-tab-btn active" onclick="setCommentMode(false)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:5px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Responder al Solicitante
                        </button>
                        <?php if (in_array($currentUser['role_name'], ['admin', 'manager', 'colaborador', 'agent', 'user'])): ?>
                            <button type="button" id="tab-reply-private" class="fd-reply-tab-btn note-btn" onclick="setCommentMode(true)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:5px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Agregar Nota Interna Privada
                            </button>
                        <?php endif; ?>
                    </div>

                    <textarea id="new-comment-text" class="form-control" rows="3" placeholder="Escriba su respuesta o detalle la acción realizada..."></textarea>
                    
                    <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:0.6rem;">
                        <button type="button" id="btn-submit-comment" class="btn-primary" onclick="handleCommentSubmit()">
                            Enviar Respuesta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

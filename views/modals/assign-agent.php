<!-- MODAL DE ASIGNACIÓN A COLABORADOR DEL ÁREA -->
<div id="assign-modal" class="modal-backdrop" style="z-index: 1100;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Asignar Ticket a Colaborador</h3>
            <button class="close-assign-modal close-modal">&times;</button>
        </div>
        
        <form id="assign-agent-form">
            <input type="hidden" id="assign-ticket-id-input" name="ticket_id">
            
            <div class="form-group">
                <label for="assign-agent-select">Seleccione el Colaborador o Coordinador:</label>
                <select id="assign-agent-select" name="agent_id" class="form-control" required style="font-size:13px;">
                    <option value="">Cargando lista de usuarios del área...</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn-secondary close-assign-modal">Cancelar</button>
                <button type="submit" class="btn-primary">Confirmar Asignación</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL DE CONFIRMACIÓN DE CAMBIO DE ESTADO (CON NOTA / COMENTARIO OPCIONAL)
     ==================================================================== -->
<div id="status-change-modal" class="modal-backdrop" style="z-index: 1150;">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <h3>Confirmar Cambio de Estado</h3>
            <button class="close-status-modal close-modal">&times;</button>
        </div>

        <form id="status-change-form">
            <input type="hidden" id="status-change-ticket-id">
            <input type="hidden" id="status-change-new-status-id">

            <div class="status-change-box">
                <div>
                    <div style="font-size:11px; color:#64748B; text-transform:uppercase; font-weight:700;">Ticket</div>
                    <strong id="status-change-code" style="color:#0284C7; font-size:14px;">-</strong>
                </div>
                <div class="status-transition-display">
                    <span id="status-change-from-badge" class="fd-status-badge">-</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#94A3B8;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    <span id="status-change-to-badge" class="fd-status-badge">-</span>
                </div>
            </div>

            <div class="form-group">
                <label>Tipo de Registro:</label>
                <div class="status-change-type-toggle">
                    <button type="button" id="status-toggle-public" class="status-type-btn active" onclick="setStatusNoteType(false)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:4px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Comentario Público (Visible al Solicitante)
                    </button>
                    <?php if (in_array($currentUser['role_name'], ['admin', 'manager', 'colaborador', 'agent', 'user'])): ?>
                    <button type="button" id="status-toggle-private" class="status-type-btn" onclick="setStatusNoteType(true)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Nota Interna Privada
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="status-change-comment">Nota o Comentario del Cambio (Opcional):</label>
                <textarea id="status-change-comment" class="form-control" rows="3" placeholder="Opcional: Ingrese una nota interna o comentario para el solicitante..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                <button type="button" class="btn-secondary close-status-modal">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar y Actualizar Estado</button>
            </div>
        </form>
    </div>
</div>

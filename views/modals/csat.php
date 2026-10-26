<div id="csat-modal" class="modal-backdrop" style="z-index: 1200;">
    <div class="csat-modal-card">
        <!-- TOP HEADER CON CSAT Y BADGE OBLIGATORIO -->
        <div class="csat-header-bar">
            <div class="csat-header-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#F59E0B" stroke="#D97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span>CSAT</span>
                <span style="color:#CBD5E1;">|</span>
                <span style="color:#64748B;">CSAT</span>
            </div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="csat-mandatory-badge">Obligatorio</span>
                <button class="close-modal close-csat-modal" style="font-size:18px; color:#94A3B8;">&times;</button>
            </div>
        </div>

        <form id="csat-form" class="csat-body">
            <input type="hidden" id="csat-ticket-id">
            <input type="hidden" id="csat-rating-value" value="5">

            <!-- PREGUNTA 1: CALIFICACIÓN CON 5 EMOJIS HORIZONTALES -->
            <h2 class="csat-question-title">
                ¿Qué tan satisfecho estás con tu experiencia de soporte el día de hoy?
            </h2>

            <div class="csat-emoji-grid">
                <!-- 1: Muy insatisfecho -->
                <div class="csat-emoji-btn" data-rating="1" onclick="selectCsatRating(1)">
                    <span class="emoji-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></span>
                    <span class="emoji-label">Muy Insatisfecho</span>
                </div>
                <!-- 2: Insatisfecho -->
                <div class="csat-emoji-btn" data-rating="2" onclick="selectCsatRating(2)">
                    <span class="emoji-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#F97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 15.5s-1-1-3-1-3 1-3 1"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></span>
                    <span class="emoji-label">Insatisfecho</span>
                </div>
                <!-- 3: Neutral -->
                <div class="csat-emoji-btn" data-rating="3" onclick="selectCsatRating(3)">
                    <span class="emoji-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></span>
                    <span class="emoji-label">Neutral</span>
                </div>
                <!-- 4: Satisfecho -->
                <div class="csat-emoji-btn" data-rating="4" onclick="selectCsatRating(4)">
                    <span class="emoji-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></span>
                    <span class="emoji-label">Satisfecho</span>
                </div>
                <!-- 5: Muy satisfecho -->
                <div class="csat-emoji-btn selected" data-rating="5" onclick="selectCsatRating(5)">
                    <span class="emoji-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 13.5c1.5 2 6.5 2 8 0"/><path d="M9 9l.01-.01"/><path d="M15 9l.01-.01"/></svg></span>
                    <span class="emoji-label">Muy Satisfecho</span>
                </div>
            </div>

            <p class="csat-subtext">Esperamos sus comentarios.</p>

            <div class="csat-divider">
                <div class="csat-divider-plus">+</div>
            </div>

            <!-- PREGUNTA 2: RESPUESTA LARGA DE TEXTO -->
            <div class="csat-comment-card">
                <div class="csat-comment-header">
                    <span>2 - 4</span>
                    <span>≡ Respuesta larga de texto</span>
                    <span>Q2</span>
                </div>
                <div class="csat-comment-prompt">
                    Gracias por tus comentarios. ¿Podrías detallar qué fue lo que más te gustó o qué podemos mejorar?
                </div>
                <textarea id="csat-feedback-text" class="form-control" rows="3" placeholder="Escriba aquí sus comentarios adicionales..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn-secondary close-csat-modal">Omitir por ahora</button>
                <button type="submit" class="btn-primary" style="background:#10B981; padding:0.65rem 1.4rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:5px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Enviar Encuesta de Satisfacción
                </button>
            </div>
        </form>
    </div>
</div>

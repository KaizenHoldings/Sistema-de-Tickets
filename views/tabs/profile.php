<?php
/**
 * Vista de Perfil de Usuario y Espacio Personal
 * Sistema de Gestión de Tickets : Kaizen
 */

$u = $userProfile['user'] ?? [
    'id' => $currentUser['id'] ?? 0,
    'name' => $currentUser['name'] ?? 'Usuario',
    'email' => $currentUser['email'] ?? '',
    'role_name' => $currentUser['role_name'] ?? 'colaborador',
    'area_name' => $currentUser['area_name'] ?? 'General',
    'created_at' => $currentUser['created_at'] ?? date('Y-m-d')
];

$counts = $userProfile['counts'] ?? [
    'assigned' => 0,
    'resolved' => 0,
    'active' => 0,
    'requested' => 0,
    'resolution_rate' => 0
];

$timing = $userProfile['timing'] ?? [
    'avg_resolution' => 'Sin datos',
    'avg_minutes' => 0,
    'fastest_resolution' => 'N/A',
    'sla_compliance' => '100%'
];

$difficulty = $userProfile['difficulty'] ?? [
    'score' => 2.0,
    'max_score' => 4.0,
    'label' => 'Media',
    'color' => '#D97706',
    'badge_class' => 'prio-medium',
    'counts' => ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
    'percentages' => ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0]
];

$activeTickets = $userProfile['active_tickets'] ?? [];
$recentActivity = $userProfile['recent_activity'] ?? [];

// Extraer iniciales para el avatar
$nameParts = preg_split('/\s+/', trim($u['name']));
$initials = '';
if (!empty($nameParts[0])) $initials .= mb_substr($nameParts[0], 0, 1);
if (count($nameParts) > 1) $initials .= mb_substr(end($nameParts), 0, 1);
$initials = strtoupper($initials) ?: 'U';

// Nombre del rol legible
$roleLabel = 'Colaborador';
$roleBadgeBg = 'rgba(79, 70, 229, 0.12)';
$roleBadgeColor = '#4338CA';
if ($u['role_name'] === 'admin') {
    $roleLabel = 'Superadministrador';
    $roleBadgeBg = 'rgba(220, 38, 38, 0.12)';
    $roleBadgeColor = '#B91C1C';
} elseif ($u['role_name'] === 'coordinador') {
    $roleLabel = 'Coordinador';
    $roleBadgeBg = 'rgba(217, 119, 6, 0.14)';
    $roleBadgeColor = '#B45309';
}

$formattedDate = date('d/m/Y', strtotime($u['created_at']));
?>

<section id="profile-tab" class="tab-content <?= ($activeTab === 'profile-tab') ? 'active' : '' ?>">
    <div class="fd-view-header">
        <div class="fd-view-title">
            <h1>Perfil de Usuario & Espacio Personal</h1>
            <p>Datos de cuenta, métricas clave de desempeño y herramientas personales de gestión</p>
        </div>
        <div class="fd-header-actions" style="display:flex; gap:0.5rem; align-items:center;">
            <button type="button" class="btn-secondary" onclick="document.getElementById('profile-password-card').scrollIntoView({behavior:'smooth'}); document.getElementById('own-current-pass').focus();">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Seguridad & Clave
            </button>
            <button type="button" class="btn-primary" onclick="switchNavTab('my-requests-tab')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                Mis Solicitudes
            </button>
        </div>
    </div>

    <!-- 1. HERO CARD: DATOS PERSONALES -->
    <div class="fd-profile-hero">
        <div class="fd-profile-hero-left">
            <div class="fd-profile-avatar-lg" title="<?= htmlspecialchars($u['name']) ?>">
                <?= htmlspecialchars($initials) ?>
                <span class="fd-profile-status-dot" title="Usuario Conectado y Activo"></span>
            </div>
            <div class="fd-profile-details">
                <div class="fd-profile-name-row">
                    <h2><?= htmlspecialchars($u['name']) ?></h2>
                    <span class="fd-role-pill" style="background:<?= $roleBadgeBg ?>; color:<?= $roleBadgeColor ?>;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px; margin-right:3px;">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                        </svg>
                        <?= htmlspecialchars($roleLabel) ?>
                    </span>
                </div>
                <div class="fd-profile-meta-grid">
                    <div class="fd-profile-meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <span><?= htmlspecialchars($u['email']) ?></span>
                    </div>
                    <div class="fd-profile-meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                        <span>Área: <strong><?= htmlspecialchars($u['area_name'] ?? 'General') ?></strong></span>
                    </div>
                    <div class="fd-profile-meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        <span>Siglas SharePoint: <strong style="color:var(--fd-action-btn);"><?= htmlspecialchars($u['alias'] ?? $u['name']) ?></strong></span>
                    </div>
                    <div class="fd-profile-meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Miembro desde: <?= htmlspecialchars($formattedDate) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="fd-profile-hero-right">
            <div class="fd-profile-quick-stats">
                <div class="fd-profile-quick-stat-item">
                    <span class="fd-stat-val" style="color:var(--fd-action-btn);"><?= (int)$counts['assigned'] ?></span>
                    <span class="fd-stat-lbl">Tickets Atendidos</span>
                </div>
                <div class="fd-stat-sep"></div>
                <div class="fd-profile-quick-stat-item">
                    <span class="fd-stat-val" style="color:#059669;"><?= (int)$counts['resolved'] ?></span>
                    <span class="fd-stat-lbl">Resueltos</span>
                </div>
                <div class="fd-stat-sep"></div>
                <div class="fd-profile-quick-stat-item">
                    <span class="fd-stat-val" style="color:#EA580C;"><?= (int)$counts['active'] ?></span>
                    <span class="fd-stat-lbl">En Proceso</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MÉTRICAS CLAVE SOLICITADAS: CANTIDAD DE TICKETS, TIEMPO PROMEDIO Y DIFICULTAD -->
    <div class="fd-profile-metrics-grid">
        
        <!-- CARD 1: CANTIDAD DE TICKETS -->
        <div class="fd-metric-box">
            <div class="fd-metric-box-header">
                <div class="fd-metric-icon" style="background: rgba(37, 99, 235, 0.1); color: #2563EB;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="fd-metric-title">Cantidad de Tickets</h3>
                    <span class="fd-metric-subtitle">Volumen total y desglose operativo</span>
                </div>
            </div>

            <div class="fd-metric-main-val">
                <span class="fd-big-number" style="color:#2563EB;"><?= (int)$counts['assigned'] ?></span>
                <span class="fd-unit">tickets asignados</span>
            </div>

            <div class="fd-metric-pill-row">
                <div class="fd-mini-pill" title="Tickets finalizados exitosamente">
                    <span class="fd-pill-label">Resueltos</span>
                    <span class="fd-pill-value" style="color:#059669;"><?= (int)$counts['resolved'] ?> (<?= $counts['resolution_rate'] ?>%)</span>
                </div>
                <div class="fd-mini-pill" title="Tickets actualmente pendientes o en progreso">
                    <span class="fd-pill-label">En Curso</span>
                    <span class="fd-pill-value" style="color:#D97706;"><?= (int)$counts['active'] ?></span>
                </div>
                <div class="fd-mini-pill" title="Solicitudes creadas por ti en la plataforma">
                    <span class="fd-pill-label">Mis Solicitudes</span>
                    <span class="fd-pill-value" style="color:#4F46E5;"><?= (int)$counts['requested'] ?></span>
                </div>
            </div>

            <div class="fd-metric-footer">
                <div class="fd-progress-track" title="Tasa de resolución: <?= $counts['resolution_rate'] ?>%">
                    <div class="fd-progress-bar" style="width: <?= min(100, max(5, $counts['resolution_rate'])) ?>%; background:#059669;"></div>
                </div>
                <div class="fd-progress-caption">
                    <span>Efectividad de cierre</span>
                    <strong><?= $counts['resolution_rate'] ?>% completado</strong>
                </div>
            </div>
        </div>

        <!-- CARD 2: TIEMPO PROMEDIO DE RESOLUCIÓN -->
        <div class="fd-metric-box">
            <div class="fd-metric-box-header">
                <div class="fd-metric-icon" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div>
                    <h3 class="fd-metric-title">Tiempo Promedio</h3>
                    <span class="fd-metric-subtitle">Velocidad y agilidad de resolución</span>
                </div>
            </div>

            <div class="fd-metric-main-val">
                <span class="fd-big-number" style="color:#059669;"><?= htmlspecialchars($timing['avg_resolution']) ?></span>
                <span class="fd-unit">por caso resuelto</span>
            </div>

            <div class="fd-metric-pill-row">
                <div class="fd-mini-pill" title="Tiempo récord en resolver un ticket">
                    <span class="fd-pill-label">Resolución más rápida</span>
                    <span class="fd-pill-value" style="color:#0284C7;">⚡ <?= htmlspecialchars($timing['fastest_resolution']) ?></span>
                </div>
                <div class="fd-mini-pill" title="Porcentaje de tickets resueltos dentro del acuerdo SLA">
                    <span class="fd-pill-label">Cumplimiento SLA</span>
                    <span class="fd-pill-value" style="color:#059669;">🛡️ <?= htmlspecialchars($timing['sla_compliance']) ?></span>
                </div>
            </div>

            <div class="fd-metric-footer">
                <div class="fd-metric-hint">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span>Medido desde la recepción del ticket hasta el cierre o paso a resuelto.</span>
                </div>
            </div>
        </div>

        <!-- CARD 3: NIVEL DE DIFICULTAD -->
        <div class="fd-metric-box">
            <div class="fd-metric-box-header">
                <div class="fd-metric-icon" style="background: rgba(217, 119, 6, 0.1); color: <?= htmlspecialchars($difficulty['color']) ?>;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                </div>
                <div>
                    <h3 class="fd-metric-title">Nivel de Dificultad</h3>
                    <span class="fd-metric-subtitle">Complejidad promedio de tickets atendidos</span>
                </div>
            </div>

            <div class="fd-metric-main-val" style="align-items: baseline;">
                <span class="fd-big-number" style="color:<?= htmlspecialchars($difficulty['color']) ?>;">
                    <?= number_format($difficulty['score'], 1) ?>
                </span>
                <span class="fd-unit">/ 4.0</span>
                <span class="badge <?= htmlspecialchars($difficulty['badge_class']) ?>" style="margin-left:0.75rem; font-size:12px; font-weight:700;">
                    <?= htmlspecialchars($difficulty['label']) ?>
                </span>
            </div>

            <!-- BARRA DE DISTRIBUCIÓN DE DIFICULTAD SEGMENTADA -->
            <div class="fd-difficulty-bar-container" title="Distribución de casos por severidad">
                <div class="fd-difficulty-bar">
                    <?php if ($difficulty['percentages']['urgent'] > 0): ?>
                        <div class="fd-diff-segment urgent" style="width: <?= $difficulty['percentages']['urgent'] ?>%;" title="Crítica: <?= $difficulty['counts']['urgent'] ?> (<?= $difficulty['percentages']['urgent'] ?>%)"></div>
                    <?php endif; ?>
                    <?php if ($difficulty['percentages']['high'] > 0): ?>
                        <div class="fd-diff-segment high" style="width: <?= $difficulty['percentages']['high'] ?>%;" title="Alta: <?= $difficulty['counts']['high'] ?> (<?= $difficulty['percentages']['high'] ?>%)"></div>
                    <?php endif; ?>
                    <?php if ($difficulty['percentages']['medium'] > 0): ?>
                        <div class="fd-diff-segment medium" style="width: <?= $difficulty['percentages']['medium'] ?>%;" title="Media: <?= $difficulty['counts']['medium'] ?> (<?= $difficulty['percentages']['medium'] ?>%)"></div>
                    <?php endif; ?>
                    <?php if ($difficulty['percentages']['low'] > 0): ?>
                        <div class="fd-diff-segment low" style="width: <?= $difficulty['percentages']['low'] ?>%;" title="Baja: <?= $difficulty['counts']['low'] ?> (<?= $difficulty['percentages']['low'] ?>%)"></div>
                    <?php endif; ?>
                    <?php if ($counts['assigned'] === 0): ?>
                        <div class="fd-diff-segment" style="width:100%; background:#E2E8F0;" title="Sin asignaciones registradas"></div>
                    <?php endif; ?>
                </div>

                <div class="fd-diff-legend">
                    <span class="fd-diff-legend-item"><i class="dot-urgent"></i> Crítica: <strong><?= (int)$difficulty['counts']['urgent'] ?></strong></span>
                    <span class="fd-diff-legend-item"><i class="dot-high"></i> Alta: <strong><?= (int)$difficulty['counts']['high'] ?></strong></span>
                    <span class="fd-diff-legend-item"><i class="dot-medium"></i> Media: <strong><?= (int)$difficulty['counts']['medium'] ?></strong></span>
                    <span class="fd-diff-legend-item"><i class="dot-low"></i> Baja: <strong><?= (int)$difficulty['counts']['low'] ?></strong></span>
                </div>
            </div>

            <div class="fd-metric-footer">
                <div class="fd-metric-hint">
                    <span>Ponderación: Crítica (4 pts), Alta (3 pts), Media (2 pts), Baja (1 pto).</span>
                </div>
            </div>
        </div>

    </div>

    <!-- 3. ESPACIO PERSONAL (WORKSPACE PERSONAL) -->
    <div class="fd-section-divider">
        <div class="fd-section-badge">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Espacio Personal de Trabajo
        </div>
        <div class="fd-section-line"></div>
    </div>

    <div class="fd-workspace-grid">
        
        <!-- COLUMNA IZQUIERDA: TAREAS ASIGNADAS ACTIVAS -->
        <div class="fd-workspace-column">
            <div class="fd-workspace-card">
                <div class="fd-card-header-styled">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div class="fd-header-icon" style="background:#EFF6FF; color:#2563EB;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 11 12 14 22 4"></polyline>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:14px; font-weight:700;">Mis Tickets Asignados en Curso</h3>
                            <span style="font-size:11px; color:var(--fd-text-muted);">Tareas prioritarias que requieren tu atención directa</span>
                        </div>
                    </div>
                    <span class="badge" style="background:#EFF6FF; color:#2563EB; font-weight:700;"><?= count($activeTickets) ?> activos</span>
                </div>

                <div class="fd-workspace-card-body">
                    <?php if (empty($activeTickets)): ?>
                        <div class="fd-empty-workspace">
                            <div class="fd-empty-icon">🎉</div>
                            <h4>¡Todo al día!</h4>
                            <p>No tienes tickets pendientes asignados en este momento. Puedes tomar nuevos tickets de la bandeja sin asignar o revisar el tablero general.</p>
                            <button type="button" class="btn-secondary" onclick="switchNavTab('tickets-tab')">Ir a Mesa de Ayuda</button>
                        </div>
                    <?php else: ?>
                        <div class="fd-active-tickets-list">
                            <?php foreach ($activeTickets as $t): 
                                $prioClass = 'prio-' . ($t['priority'] ?? 'medium');
                                $prioLabel = ucfirst($t['priority'] ?? 'Media');
                                if ($prioLabel === 'Urgent') $prioLabel = 'Crítica';
                                if ($prioLabel === 'High') $prioLabel = 'Alta';
                                if ($prioLabel === 'Medium') $prioLabel = 'Media';
                                if ($prioLabel === 'Low') $prioLabel = 'Baja';
                            ?>
                                <div class="fd-active-ticket-item" onclick="if(typeof openTicketModal==='function'){openTicketModal(<?= (int)$t['id'] ?>);}">
                                    <div class="fd-active-ticket-left">
                                        <div class="fd-active-ticket-code-row">
                                            <span class="fd-ticket-code-badge"><?= htmlspecialchars($t['code']) ?></span>
                                            <span class="badge <?= $prioClass ?>" style="font-size:10px; padding:2px 6px;"><?= htmlspecialchars($prioLabel) ?></span>
                                            <span class="fd-status-pill"><?= htmlspecialchars($t['status_name'] ?? 'En Proceso') ?></span>
                                        </div>
                                        <div class="fd-active-ticket-title">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </div>
                                        <div class="fd-active-ticket-meta">
                                            <span>👤 Solicitante: <strong><?= htmlspecialchars($t['requester_name'] ?? 'N/D') ?></strong></span>
                                            <span>🏢 Empresa: <?= htmlspecialchars($t['company_name'] ?? 'Kaizen') ?></span>
                                            <span>📅 <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="fd-active-ticket-right">
                                        <button type="button" class="btn-secondary" style="padding:0.35rem 0.65rem; font-size:11px;" title="Ver y gestionar ticket">
                                            Abrir
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- HISTORIAL DE ACTIVIDAD RECIENTE -->
            <div class="fd-workspace-card" style="margin-top: 1rem;">
                <div class="fd-card-header-styled">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div class="fd-header-icon" style="background:#F3F4F6; color:#4B5563;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 14 10"></polyline>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:14px; font-weight:700;">Mi Actividad Reciente</h3>
                            <span style="font-size:11px; color:var(--fd-text-muted);">Tus últimas intervenciones, notas y respuestas en tickets</span>
                        </div>
                    </div>
                </div>
                <div class="fd-workspace-card-body">
                    <?php if (empty($recentActivity)): ?>
                        <p style="color:var(--fd-text-muted); font-size:12px; margin:0.5rem 0; text-align:center;">Aún no has registrado respuestas o notas en tickets.</p>
                    <?php else: ?>
                        <div class="fd-activity-timeline">
                            <?php foreach ($recentActivity as $act): ?>
                                <div class="fd-activity-node">
                                    <div class="fd-activity-dot"></div>
                                    <div class="fd-activity-body">
                                        <div class="fd-activity-header">
                                            <a href="javascript:void(0)" onclick="if(typeof openTicketModal==='function'){openTicketModal(<?= (int)$act['ticket_id'] ?>);}" style="font-weight:700; color:var(--fd-action-btn); text-decoration:none;">
                                                <?= htmlspecialchars($act['ticket_code']) ?>
                                            </a>
                                            <span style="font-size:11px; color:var(--fd-text-muted);"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></span>
                                            <?php if (!empty($act['is_internal'])): ?>
                                                <span class="badge" style="background:#FEF3C7; color:#92400E; font-size:10px;">Nota Interna</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="fd-activity-snippet">
                                            <?= htmlspecialchars(mb_substr(strip_tags($act['comment_text'] ?? ''), 0, 140)) ?><?= mb_strlen($act['comment_text'] ?? '') > 140 ? '...' : '' ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: BLOC DE NOTAS PERSONAL Y SEGURIDAD -->
        <div class="fd-workspace-column">
            
            <!-- BLOC DE NOTAS PERSONAL INTERACTIVO -->
            <div class="fd-workspace-card">
                <div class="fd-card-header-styled">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div class="fd-header-icon" style="background:#FEF3C7; color:#D97706;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:14px; font-weight:700;">Bloc de Notas Personal</h3>
                            <span style="font-size:11px; color:var(--fd-text-muted);">Tus apuntes privados, recordatorios, comandos o IPs (Autoguardado)</span>
                        </div>
                    </div>
                    <span id="notepad-status-indicator" class="fd-notepad-status">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Guardado
                    </span>
                </div>

                <div class="fd-workspace-card-body" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <textarea 
                        id="user-personal-notepad" 
                        class="fd-personal-textarea" 
                        placeholder="Escribe aquí tus notas personales de soporte, procedimientos rápidos, IPs frecuentes, números de caso pendientes... (Se guarda automáticamente en tu navegador)"></textarea>
                    
                    <div class="fd-notepad-actions">
                        <button type="button" class="btn-secondary" style="font-size:11px; padding:0.3rem 0.6rem;" onclick="copyNotepadContent()">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            Copiar Notas
                        </button>
                        <button type="button" class="btn-secondary" style="font-size:11px; padding:0.3rem 0.6rem; color:#DC2626;" onclick="clearNotepadContent()">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            Limpiar
                    </div>
                </div>
            </div>

            <!-- ACTUALIZAR DATOS PERSONALES Y ALIAS SHAREPOINT -->
            <div id="profile-identity-card" class="fd-workspace-card" style="margin-top: 1rem;">
                <div class="fd-card-header-styled">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div class="fd-header-icon" style="background:#EFF6FF; color:#2563EB;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:14px; font-weight:700;">Datos Personales & Alias SharePoint</h3>
                            <span style="font-size:11px; color:var(--fd-text-muted);">Configura tu nombre real y las siglas para vincular tus tickets de SharePoint</span>
                        </div>
                    </div>
                </div>

                <div class="fd-workspace-card-body">
                    <form id="form-update-own-profile" onsubmit="handleUpdateOwnProfile(event)">
                        <div id="own-profile-alert" style="display:none; padding:0.55rem 0.85rem; border-radius:6px; font-size:12px; margin-bottom:0.75rem;"></div>

                        <div class="fd-form-group" style="margin-bottom:0.75rem;">
                            <label class="fd-form-label" for="own-display-name" style="font-size:12px; font-weight:600;">Nombre Completo *</label>
                            <input type="text" id="own-display-name" class="fd-input-text" required value="<?= htmlspecialchars($u['name']) ?>" placeholder="Tu nombre real completo" style="font-size:13px;">
                        </div>

                        <div class="fd-form-group" style="margin-bottom:1rem;">
                            <label class="fd-form-label" for="own-display-alias" style="font-size:12px; font-weight:600;">
                                Alias / Siglas SharePoint *
                                <span style="font-weight:400; color:var(--fd-text-muted);">(Para vincular tickets)</span>
                            </label>
                            <input type="text" id="own-display-alias" class="fd-input-text" required value="<?= htmlspecialchars($u['alias'] ?? $u['name']) ?>" placeholder="Ej: CM, JT, JD" style="font-size:13px;">
                            <small style="color:var(--fd-text-muted); font-size:11px; display:block; margin-top:3px;">
                                Estas siglas deben coincidir con el campo <strong>Responsable</strong> en SharePoint para que tus tickets asignados se vinculen de inmediato.
                            </small>
                        </div>

                        <div style="display:flex; justify-content:flex-end;">
                            <button type="submit" id="btn-submit-own-profile" class="btn-primary" style="font-size:12px; padding:0.45rem 1rem;">
                                Guardar Nombre y Alias
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SEGURIDAD: CAMBIAR CONTRASEÑA PROPIA -->
            <div id="profile-password-card" class="fd-workspace-card" style="margin-top: 1rem;">
                <div class="fd-card-header-styled">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div class="fd-header-icon" style="background:#FEE2E2; color:#DC2626;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:14px; font-weight:700;">Seguridad de la Cuenta</h3>
                            <span style="font-size:11px; color:var(--fd-text-muted);">Actualiza tu clave de acceso personal</span>
                        </div>
                    </div>
                </div>

                <div class="fd-workspace-card-body">
                    <form id="form-update-own-password" onsubmit="handleUpdateOwnPassword(event)">
                        <div id="own-pass-alert" style="display:none; padding:0.55rem 0.85rem; border-radius:6px; font-size:12px; margin-bottom:0.75rem;"></div>

                        <div class="fd-form-group" style="margin-bottom:0.75rem;">
                            <label class="fd-form-label" for="own-current-pass" style="font-size:12px; font-weight:600;">Contraseña Actual *</label>
                            <input type="password" id="own-current-pass" class="fd-input-text" required placeholder="Ingresa tu clave actual" style="font-size:13px;">
                        </div>

                        <div class="fd-form-group" style="margin-bottom:0.75rem;">
                            <label class="fd-form-label" for="own-new-pass" style="font-size:12px; font-weight:600;">Nueva Contraseña *</label>
                            <input type="password" id="own-new-pass" class="fd-input-text" required minlength="6" placeholder="Mínimo 6 caracteres" style="font-size:13px;">
                        </div>

                        <div class="fd-form-group" style="margin-bottom:1rem;">
                            <label class="fd-form-label" for="own-confirm-pass" style="font-size:12px; font-weight:600;">Confirmar Nueva Contraseña *</label>
                            <input type="password" id="own-confirm-pass" class="fd-input-text" required minlength="6" placeholder="Repite la nueva contraseña" style="font-size:13px;">
                        </div>

                        <div style="display:flex; justify-content:flex-end;">
                            <button type="submit" id="btn-submit-own-pass" class="btn-primary" style="font-size:12px; padding:0.45rem 1rem;">
                                Actualizar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>
</section>

<script>
// Script de gestión del espacio personal y bloc de notas
(function() {
    const userId = <?= (int)($currentUser['id'] ?? 0) ?>;
    const storageKey = 'kaizen_user_notes_' + userId;
    const notepadEl = document.getElementById('user-personal-notepad');
    const indicatorEl = document.getElementById('notepad-status-indicator');

    if (notepadEl) {
        // Cargar notas almacenadas
        try {
            const savedNotes = localStorage.getItem(storageKey);
            if (savedNotes !== null) {
                notepadEl.value = savedNotes;
            }
        } catch (e) {
            console.warn('No se pudo acceder al localStorage:', e);
        }

        // Autosave con debounce
        let timeout = null;
        notepadEl.addEventListener('input', function() {
            if (indicatorEl) {
                indicatorEl.innerHTML = '<span style="color:#D97706;">Guardando...</span>';
            }
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                try {
                    localStorage.setItem(storageKey, notepadEl.value);
                    if (indicatorEl) {
                        indicatorEl.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> <span style="color:#059669;">Guardado</span>';
                    }
                } catch (e) {
                    if (indicatorEl) {
                        indicatorEl.innerHTML = '<span style="color:#DC2626;">Error al guardar</span>';
                    }
                }
            }, 500);
        });
    }

    window.copyNotepadContent = function() {
        if (!notepadEl || !notepadEl.value) {
            alert('El bloc de notas está vacío.');
            return;
        }
        navigator.clipboard.writeText(notepadEl.value).then(() => {
            alert('¡Notas copiadas al portapapeles!');
        }).catch(() => {
            notepadEl.select();
            document.execCommand('copy');
            alert('¡Notas copiadas al portapapeles!');
        });
    };

    window.clearNotepadContent = function() {
        if (!confirm('¿Deseas vaciar todo el contenido de tu bloc de notas personal?')) {
            return;
        }
        if (notepadEl) {
            notepadEl.value = '';
            try {
                localStorage.removeItem(storageKey);
            } catch(e) {}
            if (indicatorEl) {
                indicatorEl.innerHTML = '<span style="color:#059669;">Limpio</span>';
            }
        }
    };

    window.handleUpdateOwnProfile = async function(e) {
        e.preventDefault();
        const nameVal = document.getElementById('own-display-name').value.trim();
        const aliasVal = document.getElementById('own-display-alias').value.trim();
        const alertBox = document.getElementById('own-profile-alert');
        const submitBtn = document.getElementById('btn-submit-own-profile');

        alertBox.style.display = 'none';

        if (!nameVal) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#FEE2E2';
            alertBox.style.color = '#B91C1C';
            alertBox.innerText = 'El nombre completo es requerido.';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = 'Guardando...';

        try {
            const res = await fetch('api.php?action=update_own_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: nameVal, alias: aliasVal })
            });

            const data = await res.json();

            alertBox.style.display = 'block';
            if (data.success) {
                alertBox.style.background = '#ECFDF5';
                alertBox.style.color = '#065F46';
                alertBox.innerText = data.message || 'Datos actualizados correctamente.';
                setTimeout(() => {
                    window.location.reload();
                }, 900);
            } else {
                alertBox.style.background = '#FEE2E2';
                alertBox.style.color = '#B91C1C';
                alertBox.innerText = data.message || 'Error al actualizar perfil.';
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#FEE2E2';
            alertBox.style.color = '#B91C1C';
            alertBox.innerText = 'Error de conexión con el servidor.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Guardar Nombre y Alias';
        }
    };

    window.handleUpdateOwnPassword = async function(e) {
        e.preventDefault();
        const curPass = document.getElementById('own-current-pass').value;
        const newPass = document.getElementById('own-new-pass').value;
        const confPass = document.getElementById('own-confirm-pass').value;
        const alertBox = document.getElementById('own-pass-alert');
        const submitBtn = document.getElementById('btn-submit-own-pass');

        alertBox.style.display = 'none';

        if (newPass.length < 6) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#FEE2E2';
            alertBox.style.color = '#B91C1C';
            alertBox.innerText = 'La nueva contraseña debe tener al menos 6 caracteres.';
            return;
        }

        if (newPass !== confPass) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#FEE2E2';
            alertBox.style.color = '#B91C1C';
            alertBox.innerText = 'Las nuevas contraseñas no coinciden.';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = 'Actualizando...';

        try {
            const formData = new FormData();
            formData.append('current_password', curPass);
            formData.append('new_password', newPass);

            const res = await fetch('api.php?action=update_own_password', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            alertBox.style.display = 'block';
            if (data.success) {
                alertBox.style.background = '#ECFDF5';
                alertBox.style.color = '#065F46';
                alertBox.innerText = data.message || 'Contraseña actualizada correctamente.';
                document.getElementById('form-update-own-password').reset();
            } else {
                alertBox.style.background = '#FEE2E2';
                alertBox.style.color = '#B91C1C';
                alertBox.innerText = data.message || 'Error al actualizar contraseña.';
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#FEE2E2';
            alertBox.style.color = '#B91C1C';
            alertBox.innerText = 'Error de conexión con el servidor.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Actualizar Contraseña';
        }
    };
})();
</script>

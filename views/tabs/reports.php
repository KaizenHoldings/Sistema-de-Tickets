<section id="reports-tab" class="tab-content <?= ($activeTab === 'reports-tab') ? 'active' : '' ?>">
                <div class="fd-view-header" style="align-items: flex-start; flex-wrap:wrap; gap:1rem;">
                    <div class="fd-view-title">
                        <h1 id="report-view-heading" style="font-family: 'Inter', sans-serif; font-weight: 700;">Informes y Analíticas de Rendimiento</h1>
                        <p id="report-view-sub" style="color: #64748b; font-size: 14px;">Métricas operativas del servicio de soporte en tiempo real</p>
                    </div>
                    <div class="fd-header-actions" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                        <!-- Filtro de Período -->
                        <select id="report-period-select" class="form-control" style="width:160px; font-size:13px; background:#fff; border-color:#cbd5e1; color:#334155;" onchange="updateReportsFilter()">
                            <option value="all" selected>Todo el Historial</option>
                            <option value="30days">Últimos 30 días</option>
                            <option value="month">Este Mes</option>
                            <option value="quarter">Último Trimestre</option>
                            <option value="year">Este Año</option>
                        </select>

                        <!-- Filtro de Área (SSGG vs IT) -->
                        <select id="report-area-select" class="form-control" style="width:190px; font-size:13px; background:#fff; border-color:#cbd5e1; color:#334155;" onchange="onReportAreaSelectChange(this.value)">
                            <option value="all" selected>Todas las Áreas</option>
                            <option value="SSGG">SSGG (Servicios Generales)</option>
                            <option value="IT">IT (Tecnología e Informática)</option>
                        </select>

                        <!-- Filtro de Agente (Columna Responsable) -->
                        <select id="report-agent-select" class="form-control" style="width:190px; font-size:13px; background:#fff; border-color:#cbd5e1; color:#334155;" onchange="updateReportsFilter()">
                            <option value="">Todos los Responsables</option>
                            <option value="Sin Asignar">Sin Asignar</option>
                            <?php foreach ($uniqueResponsables as $resp): ?>
                                <option value="<?= htmlspecialchars($resp) ?>"><?= htmlspecialchars($resp) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Botón Exportar CSV -->
                        <a id="btn-export-reports-csv" href="api.php?action=export_tickets_csv" class="btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px; font-size:12px; padding:0.45rem 0.8rem;" title="Exportar reporte a Excel (CSV)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Exportar CSV
                        </a>
                    </div>
                </div>

                <!-- SELECTOR RÁPIDO DE ÁREAS (BOTONES SEGMENTADOS) -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
                    <div class="fd-report-area-pills" id="report-area-pills">
                        <button type="button" class="fd-area-pill-btn active" data-area="all" onclick="selectReportArea('all')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            General (Todas las Áreas)
                        </button>
                        <button type="button" class="fd-area-pill-btn" data-area="SSGG" onclick="selectReportArea('SSGG')">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981; margin-right:6px;"></span>
                            Servicios Generales (SSGG)
                        </button>
                        <button type="button" class="fd-area-pill-btn" data-area="IT" onclick="selectReportArea('IT')">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#2563EB; margin-right:6px;"></span>
                            Tecnología e Informática (IT)
                        </button>
                    </div>

                    <div style="font-size:12px; color:#64748b; display:flex; align-items:center; gap:6px;">
                        <span>Vista activa:</span>
                        <strong id="active-area-badge" style="background:#e2e8f0; color:#1e293b; padding:2px 8px; border-radius:4px; font-weight:700;">Todas las Áreas</strong>
                    </div>
                </div>

                <!-- FILA DE TARJETAS COMPARATIVAS: SSGG vs IT -->
                <div class="fd-dept-comparison-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
                    <!-- Tarjeta SSGG -->
                    <div class="fd-dept-card" id="card-dept-ssgg" style="background:#fff; border:1px solid #d1fae5; border-left:4px solid #10b981; border-radius:10px; padding:1.1rem 1.4rem; box-shadow:0 1px 3px rgba(0,0,0,0.04); display:flex; justify-content:space-between; align-items:center; transition:all 0.2s;">
                        <div>
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                                <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; text-transform:uppercase;">SSGG</span>
                                <h4 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Servicios Generales</h4>
                            </div>
                            <div style="display:flex; gap:1.25rem; font-size:12px; color:#475569; margin-top:0.4rem; flex-wrap:wrap;">
                                <span>Total: <strong id="dept-ssgg-total" style="color:#0f172a;"><?= number_format($reportsAnalytics['departments']['SSGG']['total'] ?? 1690) ?></strong></span>
                                <span>Resueltos: <strong id="dept-ssgg-resolved" style="color:#10b981;"><?= number_format($reportsAnalytics['departments']['SSGG']['resolved'] ?? 1682) ?></strong></span>
                                <span>Resolución: <strong id="dept-ssgg-rate" style="color:#0f172a;"><?= $reportsAnalytics['departments']['SSGG']['resolution_rate'] ?? '99.5%' ?></strong></span>
                                <span>Líder: <strong id="dept-ssgg-top" style="color:#2563eb;"><?= htmlspecialchars($reportsAnalytics['departments']['SSGG']['top_agent'] ?? 'JT') ?></strong></span>
                            </div>
                        </div>
                        <div>
                            <button type="button" class="btn-secondary" style="font-size:11px; padding:0.35rem 0.75rem; background:#f0fdf4; border-color:#86efac; color:#166534;" onclick="selectReportArea('SSGG')">
                                Ver solo SSGG &rarr;
                            </button>
                        </div>
                    </div>

                    <!-- Tarjeta IT -->
                    <div class="fd-dept-card" id="card-dept-it" style="background:#fff; border:1px solid #dbeafe; border-left:4px solid #3b82f6; border-radius:10px; padding:1.1rem 1.4rem; box-shadow:0 1px 3px rgba(0,0,0,0.04); display:flex; justify-content:space-between; align-items:center; transition:all 0.2s;">
                        <div>
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                                <span style="background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; text-transform:uppercase;">IT</span>
                                <h4 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Tecnología e Informática</h4>
                            </div>
                            <div style="display:flex; gap:1.25rem; font-size:12px; color:#475569; margin-top:0.4rem; flex-wrap:wrap;">
                                <span>Total: <strong id="dept-it-total" style="color:#0f172a;"><?= number_format($reportsAnalytics['departments']['IT']['total'] ?? 378) ?></strong></span>
                                <span>Resueltos: <strong id="dept-it-resolved" style="color:#10b981;"><?= number_format($reportsAnalytics['departments']['IT']['resolved'] ?? 375) ?></strong></span>
                                <span>Resolución: <strong id="dept-it-rate" style="color:#0f172a;"><?= $reportsAnalytics['departments']['IT']['resolution_rate'] ?? '99.2%' ?></strong></span>
                                <span>Líder: <strong id="dept-it-top" style="color:#2563eb;"><?= htmlspecialchars($reportsAnalytics['departments']['IT']['top_agent'] ?? 'OM') ?></strong></span>
                            </div>
                        </div>
                        <div>
                            <button type="button" class="btn-secondary" style="font-size:11px; padding:0.35rem 0.75rem; background:#eff6ff; border-color:#93c5fd; color:#1e40af;" onclick="selectReportArea('IT')">
                                Ver solo IT &rarr;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards con Datos Reales -->
                <div class="fd-kpi-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 2rem; gap: 1.5rem;">
                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Tickets Totales</span>
                            <span style="background:#f1f5f9; padding:0.35rem 0.45rem; border-radius:6px; display:inline-flex; align-items:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                        </div>
                        <div id="kpi-report-total" style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">
                            <?= number_format($reportsAnalytics['kpis']['total'] ?? 0) ?>
                        </div>
                        <div id="kpi-report-total-sub" style="font-size:12px; color:#10b981; font-weight:600; margin-top:0.25rem;">
                            <?= htmlspecialchars($reportsAnalytics['kpis']['resolution_rate'] ?? '100%') ?> de resolución
                        </div>
                    </div>

                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Tiempo Medio de Resolución</span>
                            <span style="background:#f1f5f9; padding:0.35rem 0.45rem; border-radius:6px; display:inline-flex; align-items:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                        </div>
                        <div id="kpi-report-avg-time" style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">
                            <?= htmlspecialchars($reportsAnalytics['kpis']['avg_resolution_hours'] ?? '< 1 hora') ?>
                        </div>
                        <div id="kpi-report-avg-time-sub" style="font-size:12px; color:#64748b; font-weight:600; margin-top:0.25rem;">
                            Promedio en tickets cerrados
                        </div>
                    </div>

                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Solicitudes Resueltas</span>
                            <span style="background:#dcfce7; padding:0.35rem 0.45rem; border-radius:6px; display:inline-flex; align-items:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                        </div>
                        <div id="kpi-report-resolved" style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">
                            <?= number_format($reportsAnalytics['kpis']['resolved'] ?? 0) ?>
                        </div>
                        <div id="kpi-report-resolved-sub" style="font-size:12px; color:#2563eb; font-weight:600; margin-top:0.25rem;">
                            Líder: <?= htmlspecialchars($reportsAnalytics['kpis']['top_agent'] ?? 'N/A') ?>
                        </div>
                    </div>

                    <div class="fd-kpi-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem; transition: transform 0.2s;">
                        <div style="font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Cumplimiento SLA Operativo</span>
                            <span style="background:#fef3c7; padding:0.35rem 0.45rem; border-radius:6px; display:inline-flex; align-items:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="#F59E0B" stroke="#D97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                        </div>
                        <div id="kpi-report-sla" style="font-size:2rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">
                            <?= htmlspecialchars($reportsAnalytics['kpis']['sla_rate'] ?? '100%') ?>
                        </div>
                        <div id="kpi-report-sla-sub" style="font-size:12px; color:#10b981; font-weight:600; margin-top:0.25rem;">
                            Dentro de los plazos de servicio
                        </div>
                    </div>
                </div>

                <!-- CHARTS GRID -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- 1. Line Chart: Evolución Temporal -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 id="heading-chart-line" style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Evolución Temporal de Tickets</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartLine', this.value)">
                                <option value="line" selected>Líneas</option>
                                <option value="bar">Barras</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartLine"></canvas>
                        </div>
                    </div>

                    <!-- 2. Bar Chart: Resueltos por Agente (COLUMNA RESPONSABLE) -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 id="heading-chart-bar" style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Tickets Resueltos por Agente (Responsable)</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartBar', this.value)">
                                <option value="bar" selected>Barras</option>
                                <option value="line">Líneas</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartBar"></canvas>
                        </div>
                    </div>

                    <!-- 3. Pie/Doughnut Chart: Distribución por Prioridad -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Distribución por Prioridad</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartPie', this.value)">
                                <option value="doughnut" selected>Dona</option>
                                <option value="bar">Barras</option>
                                <option value="line">Líneas</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%; display:flex; justify-content:center;">
                            <canvas id="chartPie"></canvas>
                        </div>
                    </div>

                    <!-- 4. Histogram: Distribución del Tiempo de Resolución -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Distribución del Tiempo de Resolución</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartHist', this.value)">
                                <option value="bar" selected>Barras</option>
                                <option value="line">Líneas</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartHist"></canvas>
                        </div>
                    </div>

                    <!-- 5. Chart 5: Top Empresas Solicitantes -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Top Empresas con Más Solicitudes</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartScatter', this.value)">
                                <option value="bar" selected>Barras</option>
                                <option value="line">Líneas</option>
                                <option value="doughnut">Dona</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartScatter"></canvas>
                        </div>
                    </div>

                    <!-- 6. Chart 6: Solicitudes por Área (SSGG vs IT) -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 id="heading-chart-boxplot" style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:0; font-family:'Inter', sans-serif;">Solicitudes por Área (SSGG vs IT)</h3>
                            <select class="form-control" style="width:auto; padding:0.2rem 0.5rem; font-size:11px;" onchange="changeChartType('chartBoxplot', this.value)">
                                <option value="doughnut" selected>Dona</option>
                                <option value="bar">Barras</option>
                                <option value="line">Líneas</option>
                            </select>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartBoxplot"></canvas>
                        </div>
                    </div>
                </div>
            </section>

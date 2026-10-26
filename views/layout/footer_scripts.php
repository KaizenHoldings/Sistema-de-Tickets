<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@sgratzl/chartjs-chart-boxplot@4.2.0/build/index.umd.min.js"></script>
<script>
    window.dashboardCharts = {};
    window.currentReportsData = <?= json_encode($reportsAnalytics ?: []) ?>;

    function getBaseChartConfig(type, labels, data, labelName, colors = null) {
        const defaultPalette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b', '#14b8a6', '#f97316'];
        let bgColors;
        let borderColors;

        if (type === 'line') {
            bgColors = 'rgba(59, 130, 246, 0.12)';
            borderColors = '#3b82f6';
        } else if (type === 'doughnut') {
            bgColors = colors || defaultPalette.slice(0, labels.length);
            borderColors = '#ffffff';
        } else {
            bgColors = colors || '#3b82f6';
            borderColors = colors || '#2563eb';
        }

        return {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: labelName,
                    data: data,
                    backgroundColor: bgColors,
                    borderColor: borderColors,
                    borderWidth: type === 'line' ? 2.5 : (type === 'doughnut' ? 2 : 1),
                    borderRadius: type === 'bar' ? 6 : 0,
                    tension: type === 'line' ? 0.35 : 0,
                    fill: type === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type === 'doughnut',
                        position: 'right',
                        labels: { boxWidth: 12, font: { size: 11, family: "'Inter', sans-serif" } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        padding: 10,
                        cornerRadius: 6,
                        titleFont: { size: 12, weight: 'bold' }
                    }
                },
                scales: type === 'doughnut' ? {} : {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: '#f1f5f9' },
                        beginAtZero: true,
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        };
    }

    function renderAllCharts(analytics) {
        if (typeof Chart === 'undefined') return;
        const data = analytics || window.currentReportsData;
        if (!data || !data.charts) return;

        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#64748b";

        const ch = data.charts;

        // 1. Line Chart: Evolución Temporal
        const ctxLine = document.getElementById('chartLine');
        if (ctxLine && ch.chartLine) {
            if (window.dashboardCharts['chartLine']) window.dashboardCharts['chartLine'].destroy();
            const selectEl = document.querySelector("select[onchange=\"changeChartType('chartLine', this.value)\"]");
            const chartType = selectEl ? selectEl.value : 'line';
            const cfg = getBaseChartConfig(chartType, ch.chartLine.labels, ch.chartLine.data, 'Solicitudes Creadas', '#3b82f6');
            window.dashboardCharts['chartLine'] = new Chart(ctxLine, cfg);
            const headEl = document.getElementById('heading-chart-line');
            if (headEl && ch.chartLine.title) headEl.textContent = ch.chartLine.title;
        }

        // 2. Bar Chart: Resueltos por Agente (COLUMNA RESPONSABLE)
        const ctxBar = document.getElementById('chartBar');
        if (ctxBar && ch.chartBar) {
            if (window.dashboardCharts['chartBar']) window.dashboardCharts['chartBar'].destroy();
            const selectEl = document.querySelector("select[onchange=\"changeChartType('chartBar', this.value)\"]");
            const chartType = selectEl ? selectEl.value : 'bar';
            const cfg = getBaseChartConfig(chartType, ch.chartBar.labels, ch.chartBar.data, 'Tickets Resueltos', '#2563eb');
            window.dashboardCharts['chartBar'] = new Chart(ctxBar, cfg);
            const headEl = document.getElementById('heading-chart-bar');
            if (headEl && ch.chartBar.title) headEl.textContent = ch.chartBar.title;
        }

        // 3. Pie/Doughnut Chart: Prioridad
        const ctxPie = document.getElementById('chartPie');
        if (ctxPie && ch.chartPie) {
            if (window.dashboardCharts['chartPie']) window.dashboardCharts['chartPie'].destroy();
            const selectEl = document.querySelector("select[onchange=\"changeChartType('chartPie', this.value)\"]");
            const chartType = selectEl ? selectEl.value : 'doughnut';
            const prioColors = ['#ef4444', '#f97316', '#3b82f6', '#10b981'];
            const cfg = getBaseChartConfig(chartType, ch.chartPie.labels, ch.chartPie.data, 'Tickets', prioColors);
            window.dashboardCharts['chartPie'] = new Chart(ctxPie, cfg);
        }

        // 4. Histogram: Tiempos de Resolución
        const ctxHist = document.getElementById('chartHist');
        if (ctxHist && ch.chartHist) {
            if (window.dashboardCharts['chartHist']) window.dashboardCharts['chartHist'].destroy();
            const selectEl = document.querySelector("select[onchange=\"changeChartType('chartHist', this.value)\"]");
            const chartType = selectEl ? selectEl.value : 'bar';
            const cfg = getBaseChartConfig(chartType, ch.chartHist.labels, ch.chartHist.data, 'Casos Cerrados', '#6366f1');
            window.dashboardCharts['chartHist'] = new Chart(ctxHist, cfg);
        }

        // 5. Chart 5: Top Empresas
        const ctxScatter = document.getElementById('chartScatter');
        if (ctxScatter && ch.chartScatter) {
            if (window.dashboardCharts['chartScatter']) window.dashboardCharts['chartScatter'].destroy();
            const selectEl = document.querySelector("select[onchange=\"changeChartType('chartScatter', this.value)\"]");
            const chartType = selectEl ? selectEl.value : 'bar';
            const cfg = getBaseChartConfig(chartType, ch.chartScatter.labels, ch.chartScatter.data, 'Total Tickets', '#0ea5e9');
            window.dashboardCharts['chartScatter'] = new Chart(ctxScatter, cfg);
        }

        // 6. Chart 6: Áreas SSGG vs IT
        const ctxBox = document.getElementById('chartBoxplot');
        if (ctxBox && ch.chartBoxplot) {
            if (window.dashboardCharts['chartBoxplot']) window.dashboardCharts['chartBoxplot'].destroy();
            const selectEl = document.querySelector("select[onchange=\"changeChartType('chartBoxplot', this.value)\"]");
            const chartType = selectEl ? selectEl.value : 'doughnut';
            const areaColors = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b'];
            const cfg = getBaseChartConfig(chartType, ch.chartBoxplot.labels, ch.chartBoxplot.data, 'Solicitudes', areaColors);
            window.dashboardCharts['chartBoxplot'] = new Chart(ctxBox, cfg);
            const headEl = document.getElementById('heading-chart-boxplot');
            if (headEl && ch.chartBoxplot.title) headEl.textContent = ch.chartBoxplot.title;
        }
    }

    function changeChartType(chartId, newType) {
        if (window.dashboardCharts[chartId]) {
            let chart = window.dashboardCharts[chartId];
            let labels = Array.isArray(chart.data.labels) ? Array.from(chart.data.labels) : [];
            let rawData = Array.from(chart.data.datasets[0].data || []);
            let labelName = chart.data.datasets[0].label || 'Datos';

            chart.destroy();
            const cfg = getBaseChartConfig(newType, labels, rawData, labelName);
            cfg.options.animation = false;
            window.dashboardCharts[chartId] = new Chart(document.getElementById(chartId), cfg);
        }
    }

    function selectReportArea(areaCode) {
        const pills = document.querySelectorAll('#report-area-pills .fd-area-pill-btn');
        pills.forEach(p => {
            if (p.getAttribute('data-area') === areaCode) {
                p.classList.add('active');
            } else {
                p.classList.remove('active');
            }
        });

        const sel = document.getElementById('report-area-select');
        if (sel) sel.value = areaCode;

        updateReportsFilter();
    }

    function onReportAreaSelectChange(areaCode) {
        const pills = document.querySelectorAll('#report-area-pills .fd-area-pill-btn');
        pills.forEach(p => {
            if (p.getAttribute('data-area') === areaCode) {
                p.classList.add('active');
            } else {
                p.classList.remove('active');
            }
        });
        updateReportsFilter();
    }

    async function updateReportsFilter() {
        const periodEl = document.getElementById('report-period-select');
        const areaEl = document.getElementById('report-area-select');
        const agentEl = document.getElementById('report-agent-select');

        const period = periodEl ? periodEl.value : 'all';
        const area = areaEl ? areaEl.value : 'all';
        const responsable = agentEl ? agentEl.value : '';

        // Actualizar badge de vista activa
        const badge = document.getElementById('active-area-badge');
        if (badge) {
            if (area === 'SSGG') {
                badge.textContent = 'Servicios Generales (SSGG)';
                badge.style.background = '#dcfce7';
                badge.style.color = '#15803d';
            } else if (area === 'IT') {
                badge.textContent = 'Tecnología e Informática (IT)';
                badge.style.background = '#dbeafe';
                badge.style.color = '#1d4ed8';
            } else {
                badge.textContent = 'Todas las Áreas';
                badge.style.background = '#e2e8f0';
                badge.style.color = '#1e293b';
            }
        }

        // Resaltar tarjeta departamental activa
        const cardSSGG = document.getElementById('card-dept-ssgg');
        const cardIT = document.getElementById('card-dept-it');
        if (cardSSGG) {
            cardSSGG.style.boxShadow = area === 'SSGG' ? '0 0 0 2px #10b981, 0 4px 6px rgba(16,185,129,0.1)' : '0 1px 3px rgba(0,0,0,0.04)';
            cardSSGG.style.opacity = (area === 'IT') ? '0.6' : '1';
        }
        if (cardIT) {
            cardIT.style.boxShadow = area === 'IT' ? '0 0 0 2px #3b82f6, 0 4px 6px rgba(59,130,246,0.1)' : '0 1px 3px rgba(0,0,0,0.04)';
            cardIT.style.opacity = (area === 'SSGG') ? '0.6' : '1';
        }

        // Actualizar enlace de exportación CSV
        const exportBtn = document.getElementById('btn-export-reports-csv');
        if (exportBtn) {
            exportBtn.href = `api.php?action=export_tickets_csv&area=${encodeURIComponent(area)}`;
        }

        // Feedback visual inmediato en las tarjetas
        const cards = document.querySelectorAll('#reports-tab .fd-kpi-card');
        cards.forEach(c => { c.style.opacity = '0.5'; c.style.transform = 'scale(0.98)'; });

        try {
            const url = `api.php?action=get_reports_analytics&period=${encodeURIComponent(period)}&area=${encodeURIComponent(area)}&responsable=${encodeURIComponent(responsable)}`;
            const res = await fetch(url);
            const json = await res.json();

            if (json.success) {
                window.currentReportsData = json;

                // Actualizar valores de las tarjetas KPI
                const kpis = json.kpis || {};
                const elTotal = document.getElementById('kpi-report-total');
                const elTotalSub = document.getElementById('kpi-report-total-sub');
                const elAvgTime = document.getElementById('kpi-report-avg-time');
                const elResolved = document.getElementById('kpi-report-resolved');
                const elResolvedSub = document.getElementById('kpi-report-resolved-sub');
                const elSla = document.getElementById('kpi-report-sla');

                if (elTotal) elTotal.textContent = Number(kpis.total || 0).toLocaleString();
                if (elTotalSub) elTotalSub.textContent = (kpis.resolution_rate || '100%') + ' de resolución';
                if (elAvgTime) elAvgTime.textContent = kpis.avg_resolution_hours || '< 1 hora';
                if (elResolved) elResolved.textContent = Number(kpis.resolved || 0).toLocaleString();
                if (elResolvedSub) elResolvedSub.textContent = 'Líder: ' + (kpis.top_agent || 'N/A');
                if (elSla) elSla.textContent = kpis.sla_rate || '100%';

                // Actualizar tarjetas comparativas SSGG vs IT
                if (json.departments) {
                    const dSSGG = json.departments.SSGG || {};
                    const dIT = json.departments.IT || {};

                    const sTot = document.getElementById('dept-ssgg-total');
                    const sRes = document.getElementById('dept-ssgg-resolved');
                    const sRate = document.getElementById('dept-ssgg-rate');
                    const sTop = document.getElementById('dept-ssgg-top');
                    if (sTot) sTot.textContent = Number(dSSGG.total || 0).toLocaleString();
                    if (sRes) sRes.textContent = Number(dSSGG.resolved || 0).toLocaleString();
                    if (sRate) sRate.textContent = dSSGG.resolution_rate || '100%';
                    if (sTop) sTop.textContent = dSSGG.top_agent || 'N/A';

                    const iTot = document.getElementById('dept-it-total');
                    const iRes = document.getElementById('dept-it-resolved');
                    const iRate = document.getElementById('dept-it-rate');
                    const iTop = document.getElementById('dept-it-top');
                    if (iTot) iTot.textContent = Number(dIT.total || 0).toLocaleString();
                    if (iRes) iRes.textContent = Number(dIT.resolved || 0).toLocaleString();
                    if (iRate) iRate.textContent = dIT.resolution_rate || '100%';
                    if (iTop) iTop.textContent = dIT.top_agent || 'N/A';
                }

                // Actualizar desplegable de agentes (Responsables) para el área activa
                if (agentEl && Array.isArray(json.responsables)) {
                    const currentSelected = agentEl.value;
                    let optionsHtml = '<option value="">Todos los Responsables</option><option value="Sin Asignar">Sin Asignar</option>';
                    json.responsables.forEach(r => {
                        const sel = (r === currentSelected) ? ' selected' : '';
                        optionsHtml += `<option value="${r}"${sel}>${r}</option>`;
                    });
                    agentEl.innerHTML = optionsHtml;
                    if (!json.responsables.includes(currentSelected) && currentSelected !== '' && currentSelected !== 'Sin Asignar') {
                        agentEl.value = '';
                    }
                }

                // Renderizar gráficos con la nueva data
                renderAllCharts(json);
            } else {
                console.error("Error al obtener métricas:", json.message);
            }
        } catch (err) {
            console.error("Error de red al actualizar reportes:", err);
        } finally {
            cards.forEach(c => { c.style.opacity = '1'; c.style.transform = 'scale(1)'; });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderAllCharts();
    });
</script>
<script src="js/kanban.js?v=<?= filemtime(__DIR__ . '/../../public/js/kanban.js') ?>"></script>
<script>
    let isInternalNoteMode = false;

    function openCreateTicketModal() {
        document.getElementById('create-ticket-modal').style.display = 'flex';
    }

    function closeCreateTicketModal() {
        document.getElementById('create-ticket-modal').style.display = 'none';
    }

    function openCreateUserModal() {
        const m = document.getElementById('create-user-modal');
        if (m) {
            m.style.display = 'flex';
            m.classList.add('active');
            const f = document.getElementById('modal-create-user-form');
            if (f) f.reset();
        }
    }

    function closeCreateUserModal() {
        const m = document.getElementById('create-user-modal');
        if (m) {
            m.style.display = 'none';
            m.classList.remove('active');
        }
    }

    function closeTicketModal() {
        document.getElementById('ticket-modal').style.display = 'none';
    }

    // =========================================================================
    // CONTROLADOR DEL APARTADO DE TICKETS PENDIENTES & HOJA DE TAREAS
    // =========================================================================
    let currentPendingFilter = 'all';

    function switchPendingView(view) {
        const cardsCont = document.getElementById('pending-cards-container');
        const tableCont = document.getElementById('pending-table-container');
        const btnCards = document.getElementById('btn-pending-view-cards');
        const btnTable = document.getElementById('btn-pending-view-table');

        if (view === 'cards') {
            if (cardsCont) cardsCont.style.display = 'grid';
            if (tableCont) tableCont.style.display = 'none';
            if (btnCards) btnCards.classList.add('active');
            if (btnTable) btnTable.classList.remove('active');
        } else {
            if (cardsCont) cardsCont.style.display = 'none';
            if (tableCont) tableCont.style.display = 'block';
            if (btnCards) btnCards.classList.remove('active');
            if (btnTable) btnTable.classList.add('active');
        }
    }
    window.switchPendingView = switchPendingView;

    function setPendingFilter(filterType) {
        currentPendingFilter = filterType;
        document.querySelectorAll('#pending-filter-pills .fd-pending-pill-btn').forEach(btn => {
            if (btn.dataset.filter === filterType) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        filterPendingView();
    }
    window.setPendingFilter = setPendingFilter;

    function filterPendingView() {
        const query = (document.getElementById('pending-search-input')?.value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('#pending-cards-container .fd-task-card');
        const rows = document.querySelectorAll('#pending-table-container .pending-table-row');

        const testItem = (item) => {
            const code = item.dataset.code || '';
            const title = item.dataset.title || '';
            const desc = item.dataset.desc || '';
            const req = item.dataset.requester || '';
            const company = item.dataset.company || '';
            const agent = item.dataset.agent || '';
            const area = item.dataset.area || '';
            const statusId = parseInt(item.dataset.statusId || '1');
            const spStatus = item.dataset.spStatus || '';
            const prio = item.dataset.priority || '';
            const isMy = item.dataset.isMy === '1';
            const isUnassigned = item.dataset.isUnassigned === '1';

            if (query && !code.includes(query) && !title.includes(query) && !desc.includes(query) && !req.includes(query) && !company.includes(query) && !agent.includes(query)) {
                return false;
            }

            if (currentPendingFilter === 'my') {
                if (!isMy) return false;
            } else if (currentPendingFilter === 'unassigned') {
                if (!isUnassigned) return false;
            } else if (currentPendingFilter === 'in_progress') {
                if (statusId !== 2 && !spStatus.includes('proceso')) return false;
            } else if (currentPendingFilter === 'stand_by') {
                if (statusId !== 3 && !spStatus.includes('stand')) return false;
            } else if (currentPendingFilter === 'urgent') {
                if (prio !== 'urgent' && prio !== 'high') return false;
            } else if (currentPendingFilter === 'it') {
                if (area !== 'IT') return false;
            } else if (currentPendingFilter === 'ssgg') {
                if (area !== 'SSGG') return false;
            }

            return true;
        };

        cards.forEach(card => {
            card.style.display = testItem(card) ? '' : 'none';
        });

        rows.forEach(row => {
            row.style.display = testItem(row) ? '' : 'none';
        });
    }
    window.filterPendingView = filterPendingView;

    function setCommentMode(isInternal) {
        isInternalNoteMode = isInternal;
        const btnPublic = document.getElementById('tab-reply-public');
        const btnPrivate = document.getElementById('tab-reply-private');
        const submitBtn = document.getElementById('btn-submit-comment');

        if (isInternal) {
            btnPublic.classList.remove('active');
            if (btnPrivate) btnPrivate.classList.add('active');
            submitBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Guardar Nota Privada';
            submitBtn.style.backgroundColor = '#D97706';
        } else {
            btnPublic.classList.add('active');
            if (btnPrivate) btnPrivate.classList.remove('active');
            submitBtn.textContent = 'Enviar Respuesta';
            submitBtn.style.backgroundColor = 'var(--fd-action-btn)';
        }
    }

    function handleCommentSubmit() {
        submitComment(isInternalNoteMode);
    }

    async function updateTicketProperty(field, value) {
        const ticketId = document.getElementById('modal-ticket-id-input').value;
        if (!ticketId) return;

        try {
            const res = await fetch('api.php?action=update_ticket_properties', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: JSON.stringify({
                    ticket_id: parseInt(ticketId),
                    [field]: field === 'status_id' ? parseInt(value) : value,
                    csrf_token: window.CSRF_TOKEN
                })
            });
            const result = await res.json();
            if (result.success) {
                showToast(result.message, 'success');
                if (field === 'priority') {
                    const prioPill = document.getElementById('modal-ticket-prio-pill');
                    if (prioPill) {
                        prioPill.textContent = value;
                        prioPill.className = `fd-prio-badge fd-prio-${value}`;
                    }
                    document.querySelectorAll(`tr[data-ticket-id="${ticketId}"]`).forEach(row => {
                        row.dataset.priority = value;
                        const badge = row.querySelector('.fd-prio-badge');
                        if (badge) {
                            badge.textContent = value;
                            badge.className = `fd-prio-badge fd-prio-${value}`;
                        }
                    });
                    document.querySelectorAll(`.ticket-card[data-ticket-id="${ticketId}"]`).forEach(card => {
                        const badge = card.querySelector('.fd-prio-badge');
                        if (badge) {
                            badge.textContent = value;
                            badge.className = `fd-prio-badge fd-prio-${value}`;
                        }
                    });
                    if (typeof currentTicketData !== 'undefined' && currentTicketData && parseInt(currentTicketData.id) === parseInt(ticketId)) {
                        currentTicketData.priority = value;
                    }
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Error al actualizar propiedad.', 'error');
        }
    }

    function switchTicketsView(view) {
        const listDiv = document.getElementById('tickets-subview-list');
        const kanbanDiv = document.getElementById('tickets-subview-kanban');
        const btnList = document.getElementById('btn-toggle-list');
        const btnKanban = document.getElementById('btn-toggle-kanban');

        if (view === 'list') {
            listDiv.style.display = 'flex';
            kanbanDiv.style.display = 'none';
            btnList.classList.add('active');
            btnKanban.classList.remove('active');
            if (typeof applyAllFilters === 'function') {
                applyAllFilters(true);
            }
        } else {
            listDiv.style.display = 'none';
            kanbanDiv.style.display = 'block';
            btnKanban.classList.add('active');
            btnList.classList.remove('active');
        }
    }

    function updateUrlState(tabId, page = null) {
        if (!tabId) {
            const activeContent = document.querySelector('.tab-content.active');
            tabId = activeContent ? activeContent.id : 'dashboard-tab';
        }
        try {
            localStorage.setItem('active_tab', tabId);
        } catch(e) {}

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);

        if (tabId === 'tickets-tab') {
            const p = page || currentTicketPage || 1;
            if (p > 1) {
                url.searchParams.set('page', p);
            } else {
                url.searchParams.delete('page');
            }
            try {
                sessionStorage.setItem('tickets_current_page', String(p));
            } catch(e) {}
        } else {
            url.searchParams.delete('page');
        }

        window.history.replaceState({ tab: tabId, page: (tabId === 'tickets-tab' ? (page || currentTicketPage) : 1) }, '', url.toString());
    }
    window.updateUrlState = updateUrlState;

    function switchNavTab(tabId, updateUrl = true) {
        if (!tabId) tabId = 'dashboard-tab';
        const tabs = document.querySelectorAll('.nav-tab');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(t => {
            if (t.dataset.target === tabId) t.classList.add('active');
            else t.classList.remove('active');
        });

        contents.forEach(c => {
            if (c.id === tabId) c.classList.add('active');
            else c.classList.remove('active');
        });

        if (tabId === 'tickets-tab') {
            const heading = document.getElementById('tickets-view-heading');
            const titleSpan = heading ? heading.querySelector('span:first-child') : null;
            if (titleSpan) titleSpan.textContent = 'Todos los tickets';
            else if (heading) heading.textContent = 'Todos los tickets';
            const resetBtn = document.getElementById('reset-tickets-filter');
            if (resetBtn) resetBtn.style.display = 'none';
            if (typeof applyAllFilters === 'function') {
                applyAllFilters(true);
            }
        }

        if (tabId === 'inbox-tab' && typeof loadUnassignedTickets === 'function') {
            loadUnassignedTickets();
        }

        if (updateUrl) {
            updateUrlState(tabId, (tabId === 'tickets-tab') ? currentTicketPage : null);
        }
    }
    window.switchNavTab = switchNavTab;

    window.addEventListener('popstate', (e) => {
        const params = new URLSearchParams(window.location.search);
        const tabParam = params.get('tab') || 'dashboard-tab';
        const pageParam = parseInt(params.get('page') || '1', 10);
        if (!isNaN(pageParam) && pageParam > 0) {
            currentTicketPage = pageParam;
        }
        switchNavTab(tabParam, false);
        if (tabParam === 'tickets-tab' && typeof applyAllFilters === 'function') {
            applyAllFilters(true);
        }
    });

    // =========================================================================
    // MOTOR DE FILTRADO AVANZADO DE TICKETS (LISTA & KANBAN)
    // =========================================================================
    let currentTicketQuickTab = 'all';
    const initUrlParams = new URLSearchParams(window.location.search);
    const parsedUrlPage = parseInt(initUrlParams.get('page') || sessionStorage.getItem('tickets_current_page') || '1', 10);
    let currentTicketPage = (!isNaN(parsedUrlPage) && parsedUrlPage > 0) ? parsedUrlPage : 1;
    let currentTicketsPerPage = parseInt(localStorage.getItem('tickets_page_size') || '20', 10);
    if (isNaN(currentTicketsPerPage) || currentTicketsPerPage <= 0) currentTicketsPerPage = 20;

    function onTicketsPageSizeChange(newSize) {
        currentTicketsPerPage = parseInt(newSize, 10) || 20;
        try {
            localStorage.setItem('tickets_page_size', String(currentTicketsPerPage));
        } catch(e) {}
        currentTicketPage = 1;
        applyAllFilters(false);
        updateUrlState('tickets-tab', 1);
    }

    function onTicketFilterInput(input) {
        currentTicketPage = 1;
        const clearBtn = input.parentElement ? input.parentElement.querySelector('.fd-input-clear-btn') : null;
        if (clearBtn) {
            clearBtn.style.display = input.value.trim() ? 'block' : 'none';
        }
        applyAllFilters();
    }

    function clearFilterField(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.value = '';
            const clearBtn = input.parentElement ? input.parentElement.querySelector('.fd-input-clear-btn') : null;
            if (clearBtn) clearBtn.style.display = 'none';
        }
        applyAllFilters();
    }

    function setDateRangeQuick(range) {
        const fromInput = document.getElementById('filter-date-from');
        const toInput = document.getElementById('filter-date-to');
        const today = new Date();
        const formatDate = (d) => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        };

        document.querySelectorAll('.fd-date-quick-btn').forEach(b => b.classList.remove('active'));

        if (range === 'today') {
            if (fromInput) fromInput.value = formatDate(today);
            if (toInput) toInput.value = formatDate(today);
            if (event && event.target) event.target.classList.add('active');
        } else if (range === '7days') {
            const past = new Date();
            past.setDate(today.getDate() - 7);
            if (fromInput) fromInput.value = formatDate(past);
            if (toInput) toInput.value = formatDate(today);
            if (event && event.target) event.target.classList.add('active');
        } else if (range === 'month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            if (fromInput) fromInput.value = formatDate(firstDay);
            if (toInput) toInput.value = formatDate(today);
            if (event && event.target) event.target.classList.add('active');
        } else if (range === 'clear') {
            if (fromInput) fromInput.value = '';
            if (toInput) toInput.value = '';
        }
        applyAllFilters();
    }

    function applyQuickTab(tabKey) {
        currentTicketQuickTab = tabKey;
        currentTicketPage = 1;

        document.querySelectorAll('#fd-quick-tabs-bar .fd-quick-tab').forEach(btn => {
            if (btn.dataset.tab === tabKey) btn.classList.add('active');
            else btn.classList.remove('active');
        });

        const statusInput = document.getElementById('filter-status-input');
        const prioInput = document.getElementById('filter-priority-input');
        const respInput = document.getElementById('filter-responsable-input') || document.getElementById('filter-agent-input');
        const compInput = document.getElementById('filter-company-input') || document.getElementById('filter-requester-input');
        const areaInput = document.getElementById('filter-area-input') || document.getElementById('filter-department-input');

        if (tabKey === 'active') {
            if (statusInput) statusInput.value = 'Activos';
            if (respInput) respInput.value = '';
        } else if (tabKey === 'my') {
            if (statusInput) statusInput.value = '';
            if (respInput) respInput.value = window.CURRENT_USER_NAME || '';
        } else if (tabKey === 'unassigned') {
            if (statusInput) statusInput.value = '';
            if (respInput) respInput.value = 'Sin Asignar';
        } else if (tabKey === 'critical') {
            if (statusInput) statusInput.value = 'Pendiente';
            if (respInput) respInput.value = '';
        } else if (tabKey === 'all') {
            if (statusInput) statusInput.value = '';
            if (respInput) respInput.value = '';
        }

        applyAllFilters();
    }

    function resetAllTicketFilters() {
        const searchInput = document.getElementById('filter-global-search');
        const compInput = document.getElementById('filter-company-input') || document.getElementById('filter-requester-input');
        const fromInput = document.getElementById('filter-date-from');
        const toInput = document.getElementById('filter-date-to');
        const statusInput = document.getElementById('filter-status-input');
        const areaInput = document.getElementById('filter-area-input') || document.getElementById('filter-department-input');
        const respInput = document.getElementById('filter-responsable-input') || document.getElementById('filter-agent-input');
        const prioInput = document.getElementById('filter-priority-input');

        if (searchInput) searchInput.value = '';
        if (compInput) compInput.value = '';
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
        if (statusInput) statusInput.value = '';
        if (areaInput) areaInput.value = '';
        if (respInput) respInput.value = '';
        if (prioInput) prioInput.value = '';

        document.querySelectorAll('.fd-input-clear-btn').forEach(btn => btn.style.display = 'none');
        document.querySelectorAll('.fd-date-quick-btn').forEach(btn => btn.classList.remove('active'));

        currentTicketPage = 1;
        currentTicketQuickTab = 'all';
        document.querySelectorAll('#fd-quick-tabs-bar .fd-quick-tab').forEach(btn => {
            if (btn.dataset.tab === 'all') btn.classList.add('active');
            else btn.classList.remove('active');
        });

        applyAllFilters();
    }

    function applyAllFilters(keepPage = false) {
        if (!keepPage) {
            currentTicketPage = 1;
        }
        const query = (document.getElementById('filter-global-search')?.value || '').toLowerCase().trim();
        const company = (document.getElementById('filter-company-input')?.value || document.getElementById('filter-requester-input')?.value || '').toLowerCase().trim();
        const dateFrom = document.getElementById('filter-date-from')?.value || '';
        const dateTo = document.getElementById('filter-date-to')?.value || '';
        const statusVal = (document.getElementById('filter-status-input')?.value || '').toLowerCase().trim();
        const areaVal = (document.getElementById('filter-area-input')?.value || document.getElementById('filter-department-input')?.value || '').toLowerCase().trim();
        const responsableVal = (document.getElementById('filter-responsable-input')?.value || document.getElementById('filter-agent-input')?.value || '').toLowerCase().trim();
        const priorityVal = (document.getElementById('filter-priority-input')?.value || '').toLowerCase().trim();

        // 1. Filtrar filas de la tabla
        const rows = document.querySelectorAll('#all-tickets-tbody tr.ticket-row');
        const matchedRows = [];
        const totalCount = rows.length;

        rows.forEach(r => {
            const rowCode = r.dataset.code || '';
            const rowTitle = r.dataset.title || '';
            const rowCompany = r.dataset.company || r.dataset.requesterName || '';
            const rowArea = r.dataset.areaName || '';
            const rowResp = r.dataset.responsable || r.dataset.agentName || '';
            const rowObs = r.dataset.observations || '';
            const rowStatus = r.dataset.spStatus || r.dataset.statusName || '';
            const rowPriority = r.dataset.priority || '';
            const rowDate = r.dataset.date || '';

            let match = true;

            // Filtro 1: Buscador Global (Código, Asunto u Observaciones)
            if (query) {
                const matchCode = rowCode.includes(query);
                const matchTitle = rowTitle.includes(query);
                const matchObs = rowObs.includes(query);
                if (!matchCode && !matchTitle && !matchObs) match = false;
            }

            // Filtro 2: Empresa
            if (match && company && company !== 'todas las empresas' && company !== 'todas') {
                const matchComp = rowCompany.includes(company);
                if (!matchComp) match = false;
            }

            // Filtro 3: Fechas (Desde / Hasta)
            if (match && dateFrom && rowDate) {
                if (rowDate < dateFrom) match = false;
            }
            if (match && dateTo && rowDate) {
                if (rowDate > dateTo) match = false;
            }

            // Filtro 4: Estatus SharePoint
            if (match && statusVal && statusVal !== 'todos los estatus' && statusVal !== 'todos los estados') {
                if (statusVal === 'activos' || statusVal === 'activo') {
                    if (rowStatus.includes('procesado') || rowStatus.includes('resuelto')) match = false;
                } else {
                    const matchStatus = rowStatus.includes(statusVal);
                    if (!matchStatus) match = false;
                }
            }

            // Filtro 5: Área SharePoint (IT, SSGG)
            if (match && areaVal && areaVal !== 'todas las áreas' && areaVal !== 'todas las areas' && areaVal !== 'todas') {
                const matchArea = rowArea.includes(areaVal);
                if (!matchArea) match = false;
            }

            // Filtro 6: Responsable SharePoint
            if (match && responsableVal && responsableVal !== 'todos los responsables' && responsableVal !== 'todos los agentes') {
                if (responsableVal === 'sin asignar' || responsableVal === 'unassigned') {
                    if (rowResp && rowResp !== 'sin asignar' && rowResp !== '') match = false;
                } else {
                    const matchResp = rowResp.includes(responsableVal);
                    if (!matchResp) match = false;
                }
            }

            // Filtro 7: Prioridad (compatibilidad adicional)
            if (match && priorityVal && priorityVal !== 'todas las prioridades') {
                const matchPrio = rowPriority.includes(priorityVal) || 
                    (priorityVal.includes('urgente') && rowPriority === 'urgent') ||
                    (priorityVal.includes('alta') && rowPriority === 'high') ||
                    (priorityVal.includes('media') && rowPriority === 'medium') ||
                    (priorityVal.includes('baja') && rowPriority === 'low');
                if (!matchPrio) match = false;
            }

            if (match) {
                matchedRows.push(r);
            } else {
                r.style.display = 'none';
            }
        });

        // 1.0 Ordenar SIEMPRE del más nuevo al más antiguo (created_at DESC, id DESC)
        matchedRows.sort((a, b) => {
            const timeA = parseInt(a.dataset.timestamp || '0') || 0;
            const timeB = parseInt(b.dataset.timestamp || '0') || 0;
            if (timeB !== timeA) {
                return timeB - timeA;
            }
            const idA = parseInt(a.dataset.ticketId || '0') || 0;
            const idB = parseInt(b.dataset.ticketId || '0') || 0;
            return idB - idA;
        });

        // Reordenar en el DOM para garantizar que la presentación visual respete rigurosamente el orden más nuevo -> más antiguo
        const tbody = document.getElementById('all-tickets-tbody');
        if (tbody) {
            matchedRows.forEach(row => tbody.appendChild(row));
        }

        // 1.1 Aplicar Paginación Parametrizable (20 por defecto)
        const PAGE_SIZE = currentTicketsPerPage;
        const totalMatched = matchedRows.length;
        const totalPages = Math.max(1, Math.ceil(totalMatched / PAGE_SIZE));
        if (currentTicketPage > totalPages) currentTicketPage = totalPages;
        if (currentTicketPage < 1) currentTicketPage = 1;

        const sizeSelect = document.getElementById('tickets-page-size-select');
        if (sizeSelect && sizeSelect.value !== String(currentTicketsPerPage)) {
            sizeSelect.value = String(currentTicketsPerPage);
        }

        const startIndex = (currentTicketPage - 1) * PAGE_SIZE;
        const endIndex = startIndex + PAGE_SIZE;

        matchedRows.forEach((r, idx) => {
            if (idx >= startIndex && idx < endIndex) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });

        // Actualizar barra de paginación
        const pagBar = document.getElementById('tickets-pagination-bar');
        const pagStart = document.getElementById('pag-start-item');
        const pagEnd = document.getElementById('pag-end-item');
        const pagTotal = document.getElementById('pag-total-items');
        const pagCurrent = document.getElementById('pag-current-page');
        const pagTotalPages = document.getElementById('pag-total-pages');
        const btnPrev = document.getElementById('btn-pag-prev');
        const btnNext = document.getElementById('btn-pag-next');
        const pagPagesContainer = document.getElementById('tickets-pagination-pages');

        if (totalMatched > 0) {
            if (pagBar) pagBar.style.display = 'flex';
            if (pagStart) pagStart.textContent = startIndex + 1;
            if (pagEnd) pagEnd.textContent = Math.min(endIndex, totalMatched);
            if (pagTotal) pagTotal.textContent = totalMatched;
            if (pagCurrent) pagCurrent.textContent = currentTicketPage;
            if (pagTotalPages) pagTotalPages.textContent = totalPages;

            if (btnPrev) btnPrev.disabled = (currentTicketPage <= 1);
            if (btnNext) btnNext.disabled = (currentTicketPage >= totalPages);

            if (pagPagesContainer) {
                pagPagesContainer.innerHTML = '';
                // Mostrar botones de páginas inteligentes (máximo 5 botones)
                const startP = Math.max(1, currentTicketPage - 2);
                const endP = Math.min(totalPages, startP + 4);
                for (let p = startP; p <= endP; p++) {
                    const pBtn = document.createElement('button');
                    pBtn.type = 'button';
                    pBtn.className = (p === currentTicketPage) ? 'btn-primary' : 'btn-secondary';
                    pBtn.style.padding = '0.35rem 0.65rem';
                    pBtn.style.fontSize = '12px';
                    pBtn.onclick = () => { 
                        currentTicketPage = p; 
                        applyAllFilters(true); 
                        updateUrlState('tickets-tab', currentTicketPage);
                        const tableCard = document.getElementById('tickets-subview-list');
                        if (tableCard) {
                            tableCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    };
                }
            }
        } else {
            if (pagBar) pagBar.style.display = 'none';
        }

        // Fila de "no se encontraron resultados" en la tabla
        let emptyRow = document.getElementById('all-tickets-empty-search');
        if (totalMatched === 0 && totalCount > 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'all-tickets-empty-search';
                emptyRow.innerHTML = '<td colspan="10" style="text-align:center; padding:2.5rem; color:#64748B;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:6px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>No se encontraron tickets que coincidan con los filtros aplicados. <br><button type="button" class="btn-clear-filters" style="margin-top:0.75rem;" onclick="resetAllTicketFilters()">Restablecer filtros</button></td>';
                document.getElementById('all-tickets-tbody')?.appendChild(emptyRow);
            } else {
                emptyRow.style.display = '';
            }
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        // 2. Filtrar tarjetas en el Tablero Kanban y actualizar contadores
        const kanbanCards = document.querySelectorAll('.kanban-board .ticket-card');
        kanbanCards.forEach(card => {
            const cardCode = card.dataset.code || '';
            const cardTitle = card.dataset.title || '';
            const cardPriority = card.dataset.priority || '';
            const cardStatusId = parseInt(card.dataset.statusId || '0');
            const cardAreaId = card.dataset.areaId || '';
            const cardAgentId = parseInt(card.dataset.agentId || '0');
            const cardReqName = card.dataset.requesterName || '';
            const cardDate = card.dataset.date || '';

            let match = true;

            if (query && !cardCode.includes(query) && !cardTitle.includes(query)) match = false;
            if (match && company && !cardReqName.includes(company)) match = false;
            if (match && dateFrom && cardDate && cardDate < dateFrom) match = false;
            if (match && dateTo && cardDate && cardDate > dateTo) match = false;

            if (match && statusVal && statusVal !== 'todos los estados') {
                if (statusVal === 'activos' || statusVal === 'activo') {
                    if (cardStatusId !== 1 && cardStatusId !== 2) match = false;
                } else if (!card.textContent.toLowerCase().includes(statusVal)) {
                    match = false;
                }
            }

            if (match && priorityVal && priorityVal !== 'todas las prioridades') {
                const matchCardPrio = cardPriority.includes(priorityVal) ||
                    (priorityVal.includes('urgente') && cardPriority === 'urgent') ||
                    (priorityVal.includes('alta') && cardPriority === 'high') ||
                    (priorityVal.includes('media') && cardPriority === 'medium') ||
                    (priorityVal.includes('baja') && cardPriority === 'low');
                if (!matchCardPrio) match = false;
            }

            if (match && areaVal && areaVal !== 'todas las áreas' && areaVal !== 'todas las areas' && areaVal !== 'todas') {
                if (!card.textContent.toLowerCase().includes(areaVal)) match = false;
            }

            if (match && responsableVal && responsableVal !== 'todos los responsables' && responsableVal !== 'todos los agentes') {
                if (responsableVal === 'sin asignar' || responsableVal === 'unassigned') {
                    if (cardAgentId !== 0) match = false;
                } else if (!card.textContent.toLowerCase().includes(responsableVal)) {
                    match = false;
                }
            }

            card.style.display = match ? '' : 'none';
        });

        // Actualizar contadores por columna Kanban
        document.querySelectorAll('.kanban-column').forEach(col => {
            const countBadge = col.querySelector('.ticket-count');
            const visibleCards = col.querySelectorAll('.ticket-card:not([style*="display: none"])');
            if (countBadge) countBadge.textContent = visibleCards.length;
        });

        // 3. Actualizar contador general
        const countSpan = document.getElementById('tickets-filtered-count');
        const totalSpan = document.getElementById('tickets-total-count');
        if (countSpan) countSpan.textContent = totalMatched;
        if (totalSpan) totalSpan.textContent = totalCount;
    }
    window.applyAllFilters = applyAllFilters;
    window.onTicketsPageSizeChange = onTicketsPageSizeChange;
    window.changeTicketPage = changeTicketPage;

    function changeTicketPage(delta) {
        currentTicketPage += delta;
        applyAllFilters(true);
        updateUrlState('tickets-tab', currentTicketPage);
        // Desplazar suavemente hacia la tabla
        const tableCard = document.getElementById('tickets-subview-list');
        if (tableCard) {
            tableCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Compatibilidad para tarjetas de métricas del Dashboard
    function filterAndShowTickets(filterType) {
        switchNavTab('tickets-tab');
        switchTicketsView('list');

        const statusInput = document.getElementById('filter-status-input');
        const prioInput = document.getElementById('filter-priority-input');
        const agentInput = document.getElementById('filter-agent-input');

        if (filterType === 'all') {
            applyQuickTab('all');
        } else if (filterType === 'unresolved') {
            applyQuickTab('active');
        } else if (filterType === 'open') {
            if (statusInput) statusInput.value = 'En Progreso';
            applyAllFilters();
        } else if (filterType === 'waiting') {
            if (statusInput) statusInput.value = 'Pendiente';
            applyAllFilters();
        } else if (filterType === 'unassigned') {
            applyQuickTab('unassigned');
        } else if (filterType === 'overdue' || filterType === 'due_today') {
            applyQuickTab('critical');
        }
    }

    // Inicializar filtros al cargar el DOM respetando pestaña y página activa
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        let tabParam = params.get('tab');
        if (!tabParam) {
            try {
                tabParam = localStorage.getItem('active_tab');
            } catch(e) {}
        }
        if (tabParam && tabParam !== 'dashboard-tab') {
            switchNavTab(tabParam, false);
        }
        applyAllFilters(true);
    });

    function filterAreaChange(areaId) {
        const activeContent = document.querySelector('.tab-content.active');
        const activeTab = activeContent ? activeContent.id : 'dashboard-tab';
        const page = (activeTab === 'tickets-tab') ? (currentTicketPage || 1) : 1;
        window.location.href = 'index.php?area_id=' + encodeURIComponent(areaId) + '&tab=' + encodeURIComponent(activeTab) + '&page=' + encodeURIComponent(page);
    }

    function toggleSyncMenu(event) {
        event.stopPropagation();
        const menu = document.getElementById('fd-sync-menu');
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    document.addEventListener('click', (e) => {
        const menu = document.getElementById('fd-sync-menu');
        if (menu && menu.style.display === 'block') {
            const wrapper = document.querySelector('.fd-sync-dropdown-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                menu.style.display = 'none';
            }
        }
    });

    async function syncSharePointByRange(range, elem) {
        const menu = document.getElementById('fd-sync-menu');
        if (menu) menu.style.display = 'none';

        const mainBtn = document.getElementById('btn-sync-main');
        const toggleBtn = document.getElementById('btn-sync-dropdown-toggle');

        const rangeLabels = {
            'recent': 'Recientes (50)',
            '50': 'Últimos 50',
            '100': 'Últimos 100',
            '300': 'Últimos 300',
            'all': 'Todos los tickets',
            '24h': 'Últimas 24h',
            '1w': 'Última semana',
            '1h': 'Última hora'
        };
        const rangeText = rangeLabels[range] || range;

        if (mainBtn) {
            mainBtn.disabled = true;
            if (toggleBtn) toggleBtn.disabled = true;
            mainBtn.dataset.origHtml = mainBtn.innerHTML;
            mainBtn.innerHTML = `Sincronizando (${rangeText})...`;
        }

        try {
            let limit = 50;
            if (range === 'all') limit = 500;
            else if (range === '300') limit = 300;
            else if (range === '100') limit = 100;
            else if (range === '1w') limit = 200;
            else if (range === '24h') limit = 100;
            else limit = 50;

            const res = await fetch(`api.php?action=sync_sharepoint&limit=${limit}&range=${range}`);
            const data = await res.json();

            if (data.success) {
                const total = data.synced || 0;
                const created = data.created || 0;
                const updated = data.updated || 0;

                alert(`Sincronización completada (${rangeText}):\n• Registros procesados: ${total}\n• Nuevos creados: ${created}\n• Actualizados: ${updated}`);
                const activeContent = document.querySelector('.tab-content.active');
                const activeTab = activeContent ? activeContent.id : 'tickets-tab';
                const page = (activeTab === 'tickets-tab') ? (currentTicketPage || 1) : 1;
                window.location.href = `index.php?tab=${encodeURIComponent(activeTab)}&page=${page}`;
            } else {
                alert('Error al sincronizar con SharePoint: ' + (data.message || 'Error desconocido'));
            }
        } catch (e) {
            alert('Error de conexión al sincronizar con SharePoint: ' + e.message);
        } finally {
            if (mainBtn) {
                mainBtn.disabled = false;
                if (toggleBtn) toggleBtn.disabled = false;
                mainBtn.innerHTML = `Sincronizar (${rangeText})`;
            }
        }
    }


    // Mantener alias para compatibilidad
    function syncSharePointTickets(btn) {
        syncSharePointByRange('1w', btn);
    }

    async function clearAllTickets(btn) {
        if (!confirm('¿Estás seguro de que deseas eliminar TODOS los tickets de la base de datos?\n\nEsta acción vaciará la tabla local para que puedas realizar una sincronización limpia desde SharePoint.')) {
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Vaciando...';
        }

        try {
            const response = await fetch('api.php?action=clear_all_tickets');
            const data = await response.json();
            if (data.success) {
                alert('Base de datos de tickets vaciada correctamente.');
                const activeContent = document.querySelector('.tab-content.active');
                const activeTab = activeContent ? activeContent.id : 'tickets-tab';
                window.location.href = `index.php?tab=${encodeURIComponent(activeTab)}`;
            } else {
                alert('Error al vaciar tickets: ' + (data.message || 'Error desconocido'));
            }
        } catch (e) {
            alert('Error de conexión: ' + e.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Vaciar Tickets';
            }
        }
    }

    async function logout() {
        await fetch('api.php?action=logout');
        window.location.href = 'login.php';
    }
</script>

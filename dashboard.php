<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="card">
  <div class="card-header">
    <h3>Dashboard</h3>
    <div class="filter-bar">
      <input type="text" id="dateRange" class="form-control" placeholder="Select date range" />
      <button id="applyRange" class="btn btn-primary">Apply</button>
    </div>
  </div>
  <div class="card-body">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="text-muted">Total Revenue</div>
        <div id="kpi-revenue" style="font-size:1.6rem;font-weight:700;">₹0.00</div>
        <small class="text-muted" id="kpi-range-1"></small>
      </div>
      <div class="stat-card">
        <div class="text-muted">Total Invoices</div>
        <div id="kpi-invoices" style="font-size:1.6rem;font-weight:700;">0</div>
        <small class="text-muted" id="kpi-range-2"></small>
      </div>
      <div class="stat-card">
        <div class="text-muted">Total Patients</div>
        <div id="kpi-patients" style="font-size:1.6rem;font-weight:700;">0</div>
        <small class="text-muted" id="kpi-range-3"></small>
      </div>
      <div class="stat-card">
        <div class="text-muted">Selected Range</div>
        <div id="kpi-range" style="font-size:1rem;font-weight:600;">—</div>
        <small class="text-muted">branch-scoped for admins</small>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h4>Branch-wise Breakdown</h4></div>
  <div class="card-body">
    <div class="table-container">
      <table class="responsive-table" id="branchTable">
        <thead>
          <tr>
            <th>Branch</th>
            <th>Revenue (₹)</th>
            <th>Invoices</th>
            <th>Patients</th>
          </tr>
        </thead>
        <tbody id="branchTableBody">
          <tr><td colspan="4" class="text-center">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h4>Revenue (Daily)</h4></div>
  <div class="card-body">
    <canvas id="revenueChart" height="100"></canvas>
  </div>
</div>

<div class="card">
  <div class="card-header"><h4>Top Tests</h4></div>
  <div class="card-body">
    <canvas id="topTestsChart" height="120"></canvas>
  </div>
</div>

<script>
(function() {
  // --- Date Range Picker (Litepicker) ---
  const rangeInput = document.getElementById('dateRange');
  const today = new Date();
  const startDefault = new Date(today); startDefault.setDate(today.getDate() - 30);
  let currentStart = startDefault;
  let currentEnd   = today;

  const picker = new Litepicker({
    element: rangeInput,
    singleMode: false,
    numberOfMonths: 2,
    numberOfColumns: 2,
    format: 'YYYY-MM-DD',
    startDate: startDefault,
    endDate: today,
    maxDate: today,
    autoApply: true,
    tooltipText: { one: 'day', other: 'days' },
  });

  // --- Charts ---
  let revenueChart, topTestsChart;

  function fmtINR(n){ return '₹' + (n||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }

  function getRange() {
    const parts = rangeInput.value.split(' - ');
    if (parts.length === 2) {
      return { start: parts[0], end: parts[1] };
    }
    // fallback
    const pad = n=> String(n).padStart(2,'0');
    const s = `${currentStart.getFullYear()}-${pad(currentStart.getMonth()+1)}-${pad(currentStart.getDate())}`;
    const e = `${currentEnd.getFullYear()}-${pad(currentEnd.getMonth()+1)}-${pad(currentEnd.getDate())}`;
    return { start: s, end: e };
  }

  function loadKPIs() {
    const {start, end} = getRange();
    document.getElementById('kpi-range').textContent = `${start} → ${end}`;
    document.getElementById('kpi-range-1').textContent = `${start} → ${end}`;
    document.getElementById('kpi-range-2').textContent = `${start} → ${end}`;
    document.getElementById('kpi-range-3').textContent = `${start} → ${end}`;

    fetch(`ajax_dashboard_stats.php?start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`)
      .then(r => r.json())
      .then(data => {
        document.getElementById('kpi-revenue').textContent = fmtINR(data.kpis.revenue);
        document.getElementById('kpi-invoices').textContent = (data.kpis.invoices||0).toLocaleString('en-IN');
        document.getElementById('kpi-patients').textContent = (data.kpis.patients||0).toLocaleString('en-IN');

        const body = document.getElementById('branchTableBody');
        if (!data.by_branch || data.by_branch.length === 0) {
          body.innerHTML = `<tr><td colspan="4" class="text-center">No data</td></tr>`;
        } else {
          body.innerHTML = data.by_branch.map(b => `
            <tr>
              <td data-label="Branch">${b.name}</td>
              <td data-label="Revenue">${fmtINR(b.revenue)}</td>
              <td data-label="Invoices">${b.invoices}</td>
              <td data-label="Patients">${b.patients}</td>
            </tr>
          `).join('');
        }
      });
  }

  function loadCharts() {
    const {start, end} = getRange();
    fetch(`ajax_get_chart_data.php?start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`)
      .then(r => r.json())
      .then(data => {
        const rv = data.revenue || {labels:[], data:[]};
        const tt = data.top_tests || {labels:[], data:[]};

        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        if (revenueChart) revenueChart.destroy();
        revenueChart = new Chart(revenueCtx, {
          type: 'line',
          data: { labels: rv.labels, datasets: [{ label: 'Revenue', data: rv.data, tension: 0.3 }] },
          options: { responsive: true, plugins: { legend: { display: false } } }
        });

        const topCtx = document.getElementById('topTestsChart').getContext('2d');
        if (topTestsChart) topTestsChart.destroy();
        topTestsChart = new Chart(topCtx, {
          type: 'bar',
          data: { labels: tt.labels, datasets: [{ label: 'Count', data: tt.data }] },
          options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
        });
      });
  }

  document.getElementById('applyRange').addEventListener('click', function() {
    loadKPIs();
    loadCharts();
  });

  // Initial load
  setTimeout(() => { loadKPIs(); loadCharts(); }, 0);
})();
</script>

<script>
/* DASHBOARD-ONLY RESCUE: bulletproof hamburger toggle */
(function(){
  // find the sidebar + toggle
  const sidebar = document.querySelector('.sidebar');
  const toggleBtn = document.querySelector('.mobile-menu-toggle');
  if (!sidebar || !toggleBtn) return;

  // ensure a single overlay exists
  let overlay = document.querySelector('.mobile-menu-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'mobile-menu-overlay';
    document.body.appendChild(overlay);
  }

  // helpers
  const isOpen = () => sidebar.classList.contains('sidebar-visible');
  const open = () => {
    sidebar.classList.add('sidebar-visible');
    overlay.classList.add('visible');
    overlay.style.pointerEvents = 'auto';
    document.body.style.overflow = 'hidden';
    toggleBtn.setAttribute('aria-expanded','true');
  };
  const close = () => {
    sidebar.classList.remove('sidebar-visible');
    overlay.classList.remove('visible');
    overlay.style.pointerEvents = 'none';
    document.body.style.overflow = '';
    toggleBtn.setAttribute('aria-expanded','false');
  };
  let suppressOutside = false;
  const armSuppress = () => { suppressOutside = true; setTimeout(()=> suppressOutside = false, 220); };

  // 1) direct click on the hamburger
  toggleBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (isOpen()) {
        close();
      } else {
        armSuppress();
        open();
      }
    });





  // 3) overlay click => close
  overlay.addEventListener('click', close);

  // 4) outside click (mobile) => close
  document.addEventListener('click', function(e){
    if (window.innerWidth > 991 || !isOpen() || suppressOutside) return;
    if (!e.target.closest('.sidebar') && !e.target.closest('.mobile-menu-toggle')) close();
  }, { capture:true });

  // 5) Esc => close
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && isOpen()) close();
  });

  // 6) click any link inside sidebar => close (mobile)
  sidebar.addEventListener('click', function(e){
    if (window.innerWidth <= 991 && e.target.closest('a')) close();
  });

  // 7) desktop safety
  if (window.innerWidth >= 992) close();
  window.addEventListener('resize', () => { if (window.innerWidth >= 992) close(); });
})();
</script>

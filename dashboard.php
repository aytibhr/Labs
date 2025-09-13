<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* Custom styles for the new date filter layout */
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 1rem;
}
.filter-bar .form-group {
    flex: 1 1 180px; /* Flex-grow, flex-shrink, flex-basis */
    margin-bottom: 0; /* Remove default margin from form-group */
}
.filter-bar label {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    display: block;
}
.filter-bar .btn {
    flex-shrink: 0; /* Prevent button from shrinking */
}
@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .form-group {
        flex-basis: auto;
        width: 100%;
    }
}
</style>

<div class="card">
  <div class="card-header">
    <h3>Dashboard</h3>
    <div class="filter-bar">
      <div class="form-group">
        <label for="start_date">Start Date</label>
        <input type="text" id="start_date" class="form-control" />
      </div>
      <div class="form-group">
        <label for="end_date">End Date</label>
        <input type="text" id="end_date" class="form-control" />
      </div>
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
  // --- Date Range Pickers (Litepicker) ---
  const startDateInput = document.getElementById('start_date');
  const endDateInput = document.getElementById('end_date');
  const today = new Date();
  const startDefault = new Date();
  startDefault.setDate(today.getDate() - 30);

  const startPicker = new Litepicker({
    element: startDateInput,
    singleMode: true,
    format: 'YYYY-MM-DD',
    maxDate: today,
    onSelect: function(date) {
        if (date) {
            endPicker.setOptions({ minDate: date });
        }
    }
  });

  const endPicker = new Litepicker({
    element: endDateInput,
    singleMode: true,
    format: 'YYYY-MM-DD',
    maxDate: today,
  });

  // Set initial values
  startPicker.setDate(startDefault);
  endPicker.setDate(today);
  endPicker.setOptions({ minDate: startDefault });


  // --- Charts ---
  let revenueChart, topTestsChart;

  function fmtINR(n){ return '₹' + (n||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }

  function getRange() {
    const start = startDateInput.value;
    const end = endDateInput.value;
    return { start, end };
  }

  function loadKPIs() {
    const {start, end} = getRange();
    if (!start || !end) {
        return;
    }
    const rangeText = `${start} → ${end}`;
    document.getElementById('kpi-range').textContent = rangeText;
    document.getElementById('kpi-range-1').textContent = rangeText;
    document.getElementById('kpi-range-2').textContent = rangeText;
    document.getElementById('kpi-range-3').textContent = rangeText;

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
     if (!start || !end) {
        return;
    }
    fetch(`ajax_get_chart_data.php?start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`)
      .then(r => r.json())
      .then(data => {
        const rv = data.revenue || {labels:[], data:[]};
        const tt = data.top_tests || {labels:[], data:[]};

        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        if (revenueChart) revenueChart.destroy();
        revenueChart = new Chart(revenueCtx, {
          type: 'line',
          data: { labels: rv.labels, datasets: [{ label: 'Revenue', data: rv.data, tension: 0.3, borderColor: '#34D399', backgroundColor: 'rgba(52, 211, 153, 0.1)', fill: true }] },
          options: { responsive: true, plugins: { legend: { display: false } } }
        });

        const topCtx = document.getElementById('topTestsChart').getContext('2d');
        if (topTestsChart) topTestsChart.destroy();
        topTestsChart = new Chart(topCtx, {
          type: 'bar',
          data: { labels: tt.labels, datasets: [{ label: 'Count', data: tt.data, backgroundColor: ['#4FD1C5', '#F6AD55', '#4299E1', '#9F7AEA', '#ED64A6'] }] },
          options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
        });
      });
  }

  document.getElementById('applyRange').addEventListener('click', function() {
    loadKPIs();
    loadCharts();
  });

  // Initial load
  loadKPIs();
  loadCharts();
})();
</script>

<?php require_once "includes/footer.php"; ?>

<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

$branches = is_super_admin() ? $conn->query("SELECT id, name FROM branches ORDER BY name") : null;
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">

<div class="card">
    <div class="card-header">
        <h3>Manage Patients</h3>
    </div>
    <div class="card-body">
        <div class="invoice-filter-controls">
             <div class="filter-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="text" id="start_date" class="form-control" placeholder="YYYY-MM-DD">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="text" id="end_date" class="form-control" placeholder="YYYY-MM-DD">
                </div>
                <button id="apply-filter-btn" class="btn btn-primary">Apply Filter</button>
            </div>
            <div class="filter-row">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" id="ajax-search" class="form-control" placeholder="Search by patient name or phone...">
                </div>
                 <?php if (is_super_admin()): ?>
                <div class="form-group">
                    <label>Branch</label>
                    <select id="branch_filter" class="form-control">
                        <option value="">All Branches</option>
                        <?php if($branches) { mysqli_data_seek($branches, 0); } while($branches && $branch = $branches->fetch_assoc()): ?>
                            <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="responsive-table-container">
            <table class="responsive-table">
                <thead id="patients-table-head">
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Age</th>
                        <th>Registered On</th>
                        <?php if (is_super_admin()) echo '<th>Branch</th>'; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="patients-table-body">
                    <!-- AJAX content will be loaded here -->
                </tbody>
            </table>
        </div>
        <div class="pagination-container" id="pagination-container">
             <!-- AJAX pagination will be loaded here -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('patients-table-body');
    const paginationContainer = document.getElementById('pagination-container');
    const searchInput = document.getElementById('ajax-search');
    const branchFilter = document.getElementById('branch_filter');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const applyFilterBtn = document.getElementById('apply-filter-btn');

    new Litepicker({ element: startDateInput, format: 'YYYY-MM-DD' });
    new Litepicker({ element: endDateInput, format: 'YYYY-MM-DD' });

    function loadPatients(page = 1) {
        const query = searchInput.value;
        const branchId = branchFilter ? branchFilter.value : '';
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const colspan = document.querySelector('#patients-table-head tr').children.length;

        tableBody.classList.add('loading');

        const url = `ajax_search.php?page=patients&query=${encodeURIComponent(query)}&branch_id=${encodeURIComponent(branchId)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&p=${page}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data && data.table_html) {
                    tableBody.innerHTML = data.table_html;
                } else {
                    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">No patients found.</td></tr>`;
                }
                if (data && data.pagination_html) {
                    paginationContainer.innerHTML = data.pagination_html;
                } else {
                    paginationContainer.innerHTML = '';
                }
            })
            .catch(error => {
                console.error('Error fetching patient data:', error);
                tableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">An error occurred. Please try again.</td></tr>`;
            })
            .finally(() => {
                tableBody.classList.remove('loading');
            });
    }

    let searchTimeout;
    searchInput.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadPatients(1);
        }, 300);
    });
    
    if (branchFilter) {
        branchFilter.addEventListener('change', () => loadPatients(1));
    }
    applyFilterBtn.addEventListener('click', () => loadPatients(1));

    paginationContainer.addEventListener('click', function(e) {
        if (e.target.matches('a.page-link')) {
            e.preventDefault();
            const page = e.target.dataset.page;
            if (page) {
                loadPatients(page);
            }
        }
    });
    
    loadPatients(1);
});
</script>

<?php require_once "includes/footer.php"; ?>


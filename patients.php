<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

// Handle filters
$branch_filter = isset($_GET['branch_filter']) && !empty($_GET['branch_filter']) ? (int)$_GET['branch_filter'] : null;
$where_clauses = [];

if (is_super_admin()) {
    if ($branch_filter) {
        $where_clauses[] = "p.branch_id = $branch_filter";
    }
} else {
    $where_clauses[] = "p.branch_id = " . get_user_branch_id();
}

$sql = "SELECT p.*, b.name as branch_name FROM patients p JOIN branches b ON p.branch_id = b.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY p.created_at DESC";

$patients_result = $conn->query($sql);
$branches = is_super_admin() ? $conn->query("SELECT id, name FROM branches ORDER BY name") : null;
?>
<div class="card">
    <div class="card-header">
        <h3>Manage Patients</h3>
    </div>
    <div class="card-body">
        <div class="filter-bar" style="display:flex;gap:10px;align-items:center;margin-bottom:12px;">
          <input type="text" id="patientsDateRange" class="form-control" placeholder="Select date range" autocomplete="off">
          <button id="applyPatientsFilter" class="btn btn-primary">Apply</button>
        </div>


        <div class="filter-bar">
            <div class="form-group">
                <input type="text" id="ajax-search" data-page="patients" class="form-control" placeholder="Search by patient name or phone...">
            </div>
            <?php if (is_super_admin()): ?>
            <div class="form-group">
                <form method="GET" action="patients.php" id="branch-filter-form">
                    <select name="branch_filter" id="branch_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">Filter by Branch</option>
                        <?php while($branch = $branches->fetch_assoc()): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php if ($branch['id'] == $branch_filter) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="responsive-table-container">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Age</th>
                        <?php if (is_super_admin()) echo '<th>Branch</th>'; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($patients_result && $patients_result->num_rows > 0): ?>
                        <?php while($patient = $patients_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Name"><?php echo htmlspecialchars($patient['name']); ?></td>
                                <td data-label="Phone"><?php echo htmlspecialchars($patient['phone']); ?></td>
                                <td data-label="Age"><?php echo date_diff(date_create($patient['dob']), date_create('today'))->y; ?></td>
                                <?php if (is_super_admin()): ?>
                                    <td data-label="Branch"><?php echo htmlspecialchars($patient['branch_name']); ?></td>
                                <?php endif; ?>
                                <td data-label="Actions" class="actions">
                                    <a href="create_bill.php?step=2&patient_id=<?php echo $patient['id']; ?>">New Bill</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo is_super_admin() ? '5' : '4'; ?>" style="text-align: center;">No patients found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>
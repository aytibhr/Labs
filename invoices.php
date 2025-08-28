<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

// Handle filters
$branch_filter = isset($_GET['branch_filter']) && !empty($_GET['branch_filter']) ? (int)$_GET['branch_filter'] : null;
$view_deleted = isset($_GET['view']) && $_GET['view'] == 'deleted';

$where_clauses = [];
$where_clauses[] = $view_deleted ? "i.is_deleted = 1" : "i.is_deleted = 0";

if (is_super_admin()) {
    if ($branch_filter) {
        $where_clauses[] = "i.branch_id = $branch_filter";
    }
} else {
    $where_clauses[] = "i.branch_id = " . get_user_branch_id();
}

$sql = "SELECT i.*, p.name as patient_name, b.name as branch_name FROM invoices i JOIN patients p ON i.patient_id = p.id JOIN branches b ON i.branch_id = b.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY i.created_at DESC";

$invoices_result = $conn->query($sql);
$branches = is_super_admin() ? $conn->query("SELECT id, name FROM branches ORDER BY name") : null;
?>
<div class="card">
    <div class="card-header">
        <h3><?php echo $view_deleted ? 'Deleted Invoices' : 'Manage Invoices'; ?></h3>
        <?php if (is_super_admin()): ?>
            <a href="invoices.php?view=<?php echo $view_deleted ? 'active' : 'deleted'; ?>" class="btn btn-secondary">
                View <?php echo $view_deleted ? 'Active' : 'Trash'; ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filter-bar">
            <div class="form-group">
                <input type="text" id="ajax-search" data-page="invoices" class="form-control" placeholder="Search by invoice # or patient name...">
            </div>
            <?php if (is_super_admin()): ?>
            <div class="form-group">
                <form method="GET" action="invoices.php">
                    <input type="hidden" name="view" value="<?php echo $view_deleted ? 'deleted' : 'active'; ?>">
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
                        <th>Invoice #</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <?php if (is_super_admin()) echo '<th>Branch</th>'; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoices_result && $invoices_result->num_rows > 0): ?>
                        <?php while($invoice = $invoices_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Invoice #"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                                <td data-label="Amount">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <?php if (is_super_admin()): ?>
                                    <td data-label="Branch"><?php echo htmlspecialchars($invoice['branch_name']); ?></td>
                                <?php endif; ?>
                                <td data-label="Actions" class="actions">
                                    <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" target="_blank">View PDF</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo is_super_admin() ? '5' : '4'; ?>" style="text-align: center;">No invoices found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>
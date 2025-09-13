<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: patients.php");
    exit;
}

$patient_id = (int)$_GET['id'];
$patient = null;
$invoices = [];

// Use prepared statements to prevent SQL injection
$stmt = $conn->prepare("SELECT p.*, b.name AS branch_name FROM patients p JOIN branches b ON p.branch_id = b.id WHERE p.id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $patient = $result->fetch_assoc();
    $stmt->close();

    // Fetch invoice history for this patient
    $stmt_invoices = $conn->prepare("SELECT * FROM invoices WHERE patient_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
    $stmt_invoices->bind_param("i", $patient_id);
    $stmt_invoices->execute();
    $invoices_result = $stmt_invoices->get_result();
    while ($row = $invoices_result->fetch_assoc()) {
        $invoices[] = $row;
    }
    $stmt_invoices->close();
} else {
    // No patient found, redirect
    header("Location: patients.php");
    exit;
}
?>
<style>
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    line-height: 1.8;
}
.detail-item { margin-bottom: 0.5rem; }
.detail-item strong { color: #475569; display: block; font-size: 0.9rem; }
.patient-actions { margin-top: 1.5rem; }
</style>

<div class="card">
    <div class="card-header">
        <h3>Patient Details</h3>
        <a href="patients.php" class="btn btn-secondary">Back to Patient List</a>
    </div>
    <div class="card-body">
        <div class="details-grid">
            <div class="detail-item">
                <strong>Name:</strong>
                <span><?php echo htmlspecialchars($patient['name']); ?></span>
            </div>
             <div class="detail-item">
                <strong>Gender:</strong>
                <span><?php echo htmlspecialchars($patient['gender'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <strong>Phone:</strong>
                <span><?php echo htmlspecialchars($patient['phone']); ?></span>
            </div>
            <div class="detail-item">
                <strong>Email:</strong>
                <span><?php echo htmlspecialchars($patient['email'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <strong>Date of Birth:</strong>
                <span><?php echo date('d M, Y', strtotime($patient['dob'])); ?> (Age: <?php echo date_diff(date_create($patient['dob']), date_create('today'))->y; ?>)</span>
            </div>
            <div class="detail-item">
                <strong>Registered On:</strong>
                <span><?php echo date('d M, Y, h:i A', strtotime($patient['created_at'])); ?></span>
            </div>
            <div class="detail-item">
                <strong>Branch:</strong>
                <span><?php echo htmlspecialchars($patient['branch_name']); ?></span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <strong>Address:</strong>
                <span><?php echo nl2br(htmlspecialchars($patient['address'] ?: 'N/A')); ?></span>
            </div>
        </div>
        <?php if (!is_super_admin()): ?>
        <div class="patient-actions">
            <a href="create_bill.php?step=2&patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">Create New Bill for this Patient</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Invoice History</h3>
    </div>
    <div class="card-body">
        <div class="responsive-table-container">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoices) > 0): ?>
                        <?php foreach($invoices as $invoice): ?>
                            <tr>
                                <td data-label="Invoice #"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td data-label="Date"><?php echo date('d M, Y', strtotime($invoice['created_at'])); ?></td>
                                <td data-label="Amount">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td data-label="Actions" class="actions">
                                    <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" target="_blank" class="icon-btn" title="View PDF">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">No invoices found for this patient.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>


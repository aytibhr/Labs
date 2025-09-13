<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

if (!isset($_GET['invoice_id'])) {
    header("Location: dashboard.php");
    exit;
}

$invoice_id = (int)$_GET['invoice_id'];
$result = $conn->query("SELECT i.*, p.name as patient_name, p.phone as patient_phone FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id = $invoice_id");

if ($result->num_rows > 0) {
    $invoice = $result->fetch_assoc();
    $whatsapp_message = urlencode("Hi " . $invoice['patient_name'] . ", your invoice #" . $invoice['invoice_number'] . " for INR " . $invoice['total_amount'] . " from " . get_setting('lab_name') . " is ready. View it here: " . $_SERVER['HTTP_HOST'] . '/' . $invoice['pdf_path']);
    $sms_message = urlencode("Hi " . $invoice['patient_name'] . ", Invoice: " . $invoice['invoice_number'] . ", Amt: INR " . $invoice['total_amount'] . ". PDF: " . $_SERVER['HTTP_HOST'] . '/' . $invoice['pdf_path']);
} else {
    // Invoice not found, redirect
    header("Location: dashboard.php");
    exit;
}
?>

<div class="card success-container">
    <div class="card-body text-center">
        <div class="success-icon">✓</div>
        <h2>Invoice Generated Successfully!</h2>
        <p>Invoice Number: <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></p>
        <div class="success-actions">
            <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" target="_blank" class="btn btn-secondary">View Invoice</a>
            <a href="https://api.whatsapp.com/send?phone=91<?php echo htmlspecialchars($invoice['patient_phone']); ?>&text=<?php echo $whatsapp_message; ?>" target="_blank" class="btn btn-secondary">Send to WhatsApp</a>
            <a href="sms:+91<?php echo htmlspecialchars($invoice['patient_phone']); ?>?&body=<?php echo $sms_message; ?>" class="btn btn-secondary">Send SMS</a>
            <a href="create_bill.php" class="btn btn-primary">Create New Bill</a>
        </div>
    </div>
</div>

<style>
.success-container {
    max-width: 600px;
    margin: 40px auto;
}
.success-icon {
    font-size: 50px;
    color: var(--brand);
    background-color: #e6fffa;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.success-actions {
    margin-top: 30px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
}
.success-actions .btn {
    flex-grow: 1;
    min-width: 150px;
}
</style>

<?php require_once "includes/footer.php"; ?>

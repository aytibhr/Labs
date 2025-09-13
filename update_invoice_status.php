<?php
require_once "includes/functions.php";
redirect_if_not_logged_in();

// We expect a JSON response
header('Content-Type: application/json');

// Only superadmins or admins can perform this action
if (!is_super_admin() && !is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $invoice_id = (int)$_GET['id'];

    // Fetch the invoice to get total_amount
    $invoice_res = $conn->query("SELECT total_amount FROM invoices WHERE id = $invoice_id");
    if ($invoice_res && $invoice_res->num_rows > 0) {
        $invoice = $invoice_res->fetch_assoc();
        $total_amount = $invoice['total_amount'];

        // Update the invoice to be fully paid
        $stmt = $conn->prepare("UPDATE invoices SET status = 'Completed', amount_paid = ?, balance_due = 0 WHERE id = ?");
        $stmt->bind_param("di", $total_amount, $invoice_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invoice not found.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method or missing ID.']);
}
?>


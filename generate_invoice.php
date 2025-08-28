<?php
// Enable error reporting for diagnostics
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "includes/functions.php";
require_once "lib/fpdf/fpdf.php"; // Make sure this path is correct
redirect_if_not_logged_in();

// --- Create a custom PDF class for cleaner headers and footers ---
class PDF extends FPDF {
    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, htmlspecialchars(get_setting('invoice_footer_note')), 0, 0, 'C');
    }
}

if (isset($_POST['generate_invoice'])) {
    // --- Collect Data ---
    $patient_id = (int)$_POST['patient_id'];
    $test_ids_str = $_POST['test_ids'];
    $total_amount = (float)$_POST['total_amount'];
    $payment_method = $_POST['payment_method'];
    $cash_received = !empty($_POST['cash_received']) ? (float)$_POST['cash_received'] : null;
    $balance_returned = ($payment_method == 'Cash' && $cash_received) ? $cash_received - $total_amount : null;
    $user_id = get_user_id();
    
    // --- Use the patient's branch_id for invoice, not the admin's session ---
    $patient_branch_result = $conn->query("SELECT branch_id FROM patients WHERE id = $patient_id");
    $branch_id = $patient_branch_result->fetch_assoc()['branch_id'];
    
    $invoice_number = generate_invoice_number($branch_id);

    // --- Database Transaction ---
    $conn->begin_transaction();
    try {
        // Insert into invoices table
        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, total_amount, payment_method, cash_received, balance_returned, created_by, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siddsdii", $invoice_number, $patient_id, $total_amount, $payment_method, $cash_received, $balance_returned, $user_id, $branch_id);
        $stmt->execute();
        $invoice_id = $stmt->insert_id;
        
        // Insert into invoice_items table
        if (!empty($test_ids_str)) {
            $tests = $conn->query("SELECT id, test_name, price FROM lab_tests WHERE id IN ($test_ids_str)");
            $stmt_items = $conn->prepare("INSERT INTO invoice_items (invoice_id, test_id, test_name_snapshot, price_snapshot) VALUES (?, ?, ?, ?)");
            while ($test = $tests->fetch_assoc()) {
                $stmt_items->bind_param("iisd", $invoice_id, $test['id'], $test['test_name'], $test['price']);
                $stmt_items->execute();
            }
        }

        // --- Generate PDF ---
        $patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

        $pdf = new PDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Image(get_setting('lab_logo_path'), 10, 6, 30);
        $pdf->Cell(0, 10, htmlspecialchars(get_setting('lab_name')) . ' - Tax Invoice', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, htmlspecialchars(get_setting('lab_address')), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Phone: ' . htmlspecialchars(get_setting('lab_phone')), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Invoice Details
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 7, 'Invoice No:');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 7, $invoice_number, 0, 1);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 7, 'Date:');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 7, date('d M, Y, g:i A'), 0, 1);
        $pdf->Ln(5);

        // Patient Details
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, 'Billed To:', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 7, htmlspecialchars($patient['name']), 0, 1);
        $pdf->Cell(0, 7, 'Phone: ' . htmlspecialchars($patient['phone']), 0, 1);
        $pdf->Ln(10);

        // Table Header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(130, 10, 'Test Name', 1, 0, 'C');
        $pdf->Cell(60, 10, 'Price (INR)', 1, 1, 'C');

        // Table Items
        $pdf->SetFont('Arial', '', 12);
        $items_result = $conn->query("SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
        while ($item = $items_result->fetch_assoc()) {
            $pdf->Cell(130, 10, ' ' . htmlspecialchars($item['test_name_snapshot']), 1);
            $pdf->Cell(60, 10, number_format($item['price_snapshot'], 2) . ' ', 1, 1, 'R');
        }

        // Total
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(130, 10, 'Total', 1);
        $pdf->Cell(60, 10, number_format($total_amount, 2) . ' ', 1, 1, 'R');
        $pdf->Ln(10);

        // Payment Details
        $pdf->Cell(0, 7, 'Payment Method: ' . $payment_method, 0, 1);
        if ($payment_method == 'Cash') {
            $pdf->Cell(0, 7, 'Cash Received: ' . number_format($cash_received, 2), 0, 1);
            $pdf->Cell(0, 7, 'Balance Returned: ' . number_format($balance_returned, 2), 0, 1);
        }
        
        $pdf_path = "invoices_pdf/" . $invoice_number . ".pdf";
        $pdf->Output('F', $pdf_path); // 'F' saves the file to the server
        
        // Update invoice record with PDF path
        $conn->query("UPDATE invoices SET pdf_path = '$pdf_path' WHERE id = $invoice_id");

        // --- Commit and Show Success ---
        $conn->commit();

        // Redirect to a success page to avoid resubmission on refresh
        header("Location: success.php?invoice_id=" . $invoice_id);
        exit;

    } catch (Exception $exception) {
        $conn->rollback();
        // Redirect to an error page or show a message
        die("Error creating invoice: " . $exception->getMessage() . ". Please check file paths and permissions.");
    }
} else {
    // If accessed directly, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}
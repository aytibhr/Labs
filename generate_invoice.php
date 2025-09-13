<?php
// Enable error reporting for diagnostics
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "includes/functions.php";
require_once "lib/fpdf/fpdf.php";
redirect_if_not_logged_in();

class PDF extends FPDF {
    public $brandColor = [34, 193, 195]; // A nice teal color

    function Header() {
        // Set background
        $this->SetFillColor(249, 250, 251);
        $this->Rect(0, 0, 210, 50, 'F');
        
        // Logo and Company Details
        $logoPath = get_setting('lab_logo_path');
        if ($logoPath && file_exists($logoPath)) {
            $this->Image($logoPath, 105-15, 8, 30, 0, 'PNG');
        }
        $this->Ln(28);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 7, get_setting('lab_name'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, get_setting('lab_address'), 0, 1, 'C');
        $this->Cell(0, 5, 'Phone: ' . get_setting('lab_phone'), 0, 1, 'C');
        
        // Reset Y position for main content
        $this->SetY(60);
    }

    function Footer() {
        $this->SetY(-20);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(156, 163, 175);
        $this->MultiCell(0, 5, "Note: " . get_setting('invoice_footer_note'), 0, 'C');
        $this->SetY(-15);
        $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
    }

    function InvoiceTitle($number, $date) {
        $this->SetFont('Arial','B',24);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 10, 'INVOICE', 0, 1, 'L');
        $this->SetFont('Arial','',10);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, 'Invoice #: ' . $number, 0, 1, 'L');
        $this->Cell(0, 6, 'Date: ' . $date, 0, 1, 'L');
        $this->Ln(12);
    }
    
    function BilledTo($patient) {
        $this->SetFont('Arial','B',10);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, 'BILLED TO', 0, 1, 'L');
        $this->SetFont('Arial','B',12);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 7, $patient['name'], 0, 1, 'L');
        $this->SetFont('Arial','',10);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, 'Phone: ' . $patient['phone'], 0, 1, 'L');
        $this->Cell(0, 6, 'Age: ' . date_diff(date_create($patient['dob']), date_create('today'))->y . ' years', 0, 1, 'L');
        $this->Ln(12);
    }
    
    function ItemsTable($header, $items) {
        $this->SetFont('Arial','B',10);
        $this->SetFillColor($this->brandColor[0], $this->brandColor[1], $this->brandColor[2]);
        $this->SetTextColor(255);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        
        $w = array(110, 40, 40); // Widths of columns
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        $this->Ln();

        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Arial','',10);
        $fill = false;
        foreach($items as $row) {
            $this->SetFillColor(248, 250, 252);
            $this->Cell($w[0], 10, '  ' . $row['test_name_snapshot'], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 10, '1 ', 'LR', 0, 'C', $fill);
            $this->Cell($w[2], 10, number_format($row['price_snapshot'], 2) . '  ', 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(1);
    }

    function SummaryRow($label, $value, $is_total = false) {
        $this->SetFont('Arial', $is_total ? 'B' : '', 10);
        $this->SetTextColor($is_total ? 30 : 100, $is_total ? 41 : 116, $is_total ? 59 : 139);
        $this->Cell(150, 8, $label, 0, 0, 'R');
        $this->Cell(40, 8, number_format($value, 2) . '  ', 0, 1, 'R');
    }
}


if (isset($_POST['generate_invoice'])) {
    // --- Collect Data ---
    $patient_id = (int)$_POST['patient_id'];
    $test_ids_str = $_POST['test_ids'];
    $total_amount = (float)$_POST['total_amount'];
    $payment_method = $_POST['payment_method'];
    $initial_payment = !empty($_POST['initial_payment']) ? (float)$_POST['initial_payment'] : 0.00;
    $user_id = get_user_id();
    
    $patient_branch_result = $conn->query("SELECT branch_id FROM patients WHERE id = $patient_id");
    $branch_id = $patient_branch_result->fetch_assoc()['branch_id'];
    
    $invoice_number = generate_invoice_number();
    $amount_paid = ($payment_method == 'UPI') ? $total_amount : $initial_payment;
    $balance_due = $total_amount - $amount_paid;
    $status = ($balance_due <= 0.01) ? 'Completed' : 'Pending'; // Use a small tolerance for float comparison

    // --- Database Transaction ---
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, total_amount, amount_paid, status, payment_method, initial_payment, balance_due, created_by, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siddssddii", $invoice_number, $patient_id, $total_amount, $amount_paid, $status, $payment_method, $initial_payment, $balance_due, $user_id, $branch_id);
        $stmt->execute();
        $invoice_id = $stmt->insert_id;
        
        $invoice_items_data = [];
        if (!empty($test_ids_str)) {
            $tests = $conn->query("SELECT id, test_name, test_code, price FROM lab_tests WHERE id IN ($test_ids_str)");
            $stmt_items = $conn->prepare("INSERT INTO invoice_items (invoice_id, test_id, test_name_snapshot, price_snapshot) VALUES (?, ?, ?, ?)");
            while ($test = $tests->fetch_assoc()) {
                $stmt_items->bind_param("iisd", $invoice_id, $test['id'], $test['test_name'], $test['price']);
                $stmt_items->execute();
                $invoice_items_data[] = ['test_name_snapshot' => $test['test_name'], 'price_snapshot' => $test['price']];
            }
        }

        $patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

        $pdf = new PDF('P','mm','A4');
        $pdf->AddPage();
        $pdf->InvoiceTitle($invoice_number, date('d M, Y'));
        $pdf->BilledTo($patient);
        $header = array('Test Description', 'Qty', 'Amount (INR)');
        $pdf->ItemsTable($header, $invoice_items_data);
        
        $pdf->SummaryRow('Subtotal', $total_amount);
        $pdf->SummaryRow('Amount Paid', $amount_paid);
        $pdf->SetFont('Arial','B',12);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFillColor(229, 231, 235);
        $pdf->Cell(150, 10, 'Balance Due', 0, 0, 'R', true);
        $pdf->Cell(40, 10, number_format($balance_due, 2) . '  ', 0, 1, 'R', true);
        
        $pdf_path = "invoices_pdf/" . $invoice_number . ".pdf";
        $pdf->Output('F', $pdf_path);
        
        $conn->query("UPDATE invoices SET pdf_path = '$pdf_path' WHERE id = $invoice_id");

        $conn->commit();

        header("Location: success.php?invoice_id=" . $invoice_id);
        exit;

    } catch (Exception $exception) {
        $conn->rollback();
        die("Error creating invoice: " . $exception->getMessage());
    }
} else {
    header("Location: dashboard.php");
    exit;
}


<?php
require_once "includes/functions.php";
redirect_if_not_logged_in();
header('Content-Type: application/json');

$start = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end   = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Default to last 30 days if not provided
if (!$start || !$end) {
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime('-30 days', strtotime($end)));
}

$branch_condition = is_super_admin() ? "" : " AND i.branch_id = " . (int)get_user_branch_id();
$branch_condition_pat = is_super_admin() ? "" : " AND p.branch_id = " . (int)get_user_branch_id();

$start_esc = $conn->real_escape_string($start);
$end_esc   = $conn->real_escape_string($end);
$date_cond_invoices = " AND DATE(i.created_at) BETWEEN '$start_esc' AND '$end_esc' ";

// KPIs (using invoices timeline)
// Total Revenue
$sql = "SELECT COALESCE(SUM(i.total_amount),0) AS total_revenue
        FROM invoices i
        WHERE i.is_deleted = 0 $branch_condition $date_cond_invoices";
$total_revenue = (float)($conn->query($sql)->fetch_assoc()['total_revenue'] ?? 0);

// Total Invoices
$sql = "SELECT COUNT(*) AS total_invoices
        FROM invoices i
        WHERE i.is_deleted = 0 $branch_condition $date_cond_invoices";
$total_invoices = (int)($conn->query($sql)->fetch_assoc()['total_invoices'] ?? 0);

// Total Patients (unique patients with invoices in the range)
$sql = "SELECT COUNT(DISTINCT i.patient_id) AS total_patients
        FROM invoices i
        WHERE i.is_deleted = 0 $branch_condition $date_cond_invoices";
$total_patients = (int)($conn->query($sql)->fetch_assoc()['total_patients'] ?? 0);

// Branch-wise breakdown
$branch_rows = [];
$sql = "SELECT b.id, b.name,
               COALESCE(SUM(i.total_amount),0) AS revenue,
               COUNT(i.id) AS invoices,
               COUNT(DISTINCT i.patient_id) AS patients
        FROM branches b
        LEFT JOIN invoices i ON i.branch_id = b.id
             AND i.is_deleted = 0
             $date_cond_invoices
        " . (is_super_admin() ? "" : (" WHERE b.id = " . (int)get_user_branch_id())) . "
        GROUP BY b.id, b.name
        ORDER BY revenue DESC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $branch_rows[] = [
        'id'       => (int)$row['id'],
        'name'     => $row['name'],
        'revenue'  => (float)$row['revenue'],
        'invoices' => (int)$row['invoices'],
        'patients' => (int)$row['patients'],
    ];
}

echo json_encode([
    'start_date'     => $start,
    'end_date'       => $end,
    'kpis' => [
        'revenue'  => $total_revenue,
        'invoices' => $total_invoices,
        'patients' => $total_patients,
    ],
    'by_branch' => $branch_rows,
]);

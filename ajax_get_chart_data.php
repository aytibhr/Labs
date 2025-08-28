<?php
require_once "includes/functions.php";
redirect_if_not_logged_in();
header('Content-Type: application/json');

$start = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end   = isset($_GET['end_date']) ? $_GET['end_date'] : null;

if (!$start || !$end) {
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime('-30 days', strtotime($end)));
}

$start_esc = $conn->real_escape_string($start);
$end_esc   = $conn->real_escape_string($end);

$branch_condition = is_super_admin() ? "" : " AND i.branch_id = " . (int)get_user_branch_id();
$date_condition   = " AND DATE(i.created_at) BETWEEN '$start_esc' AND '$end_esc' ";

// Revenue by day (labels as d M)
$revenue_sql = "SELECT DATE(i.created_at) AS day, SUM(i.total_amount) AS total
                FROM invoices i
                WHERE i.is_deleted = 0 $branch_condition $date_condition
                GROUP BY DATE(i.created_at)
                ORDER BY DATE(i.created_at) ASC";
$res = $conn->query($revenue_sql);
$labels = []; $series = [];
while ($row = $res->fetch_assoc()) {
    $labels[] = date('d M', strtotime($row['day']));
    $series[] = (float)$row['total'];
}

// Top tests by count
$top_tests_sql = "SELECT ii.test_name_snapshot, COUNT(*) AS cnt
                  FROM invoice_items ii
                  JOIN invoices i ON ii.invoice_id = i.id
                  WHERE i.is_deleted = 0 $branch_condition $date_condition
                  GROUP BY ii.test_id, ii.test_name_snapshot
                  ORDER BY cnt DESC
                  LIMIT 5";
$res2 = $conn->query($top_tests_sql);
$top_labels = []; $top_counts = [];
while ($row = $res2->fetch_assoc()) {
    $top_labels[] = $row['test_name_snapshot'];
    $top_counts[] = (int)$row['cnt'];
}

echo json_encode([
  'revenue'  => ['labels' => $labels, 'data' => $series],
  'top_tests'=> ['labels' => $top_labels, 'data' => $top_counts]
]);

<?php
require_once "includes/functions.php";
redirect_if_not_logged_in();

// We will always return a JSON object
header('Content-Type: application/json');

// --- SAFE PARAMETER HANDLING ---
$page_slug = $_GET['page'] ?? '';
$query_raw = $_GET['query'] ?? '';
$query = $conn->real_escape_string($query_raw);
$branch_id = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : null;
$status = isset($_GET['status']) && $_GET['status'] == 'pending' ? 'Pending' : 'Completed';
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$records_per_page = 15;

// --- INITIALIZE RESPONSE ---
$response = [
    'table_html' => '',
    'pagination_html' => ''
];

// This is a helper function to safely bind parameters to a statement, compatible with older PHP versions.
function safe_bind_param($stmt, $types, &$params) {
    if (empty($params)) {
        return;
    }
    $bind_names = [$types];
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// --- MAIN SWITCH FOR DIFFERENT PAGES ---
switch ($page_slug) {

    // --- PATIENTS PAGE LOGIC ---
    case 'patients':
        $base_sql = "FROM patients p LEFT JOIN branches b ON p.branch_id = b.id";
        $where_clauses = [];
        $params = [];
        $types = "";

        if (!empty($query)) {
            $where_clauses[] = "(p.name LIKE ? OR p.phone LIKE ?)";
            $search_query = "%" . $query . "%";
            $params[] = $search_query;
            $params[] = $search_query;
            $types .= "ss";
        }
        if (is_super_admin()) {
            if ($branch_id) {
                $where_clauses[] = "p.branch_id = ?";
                $params[] = $branch_id;
                $types .= "i";
            }
        } else {
            $where_clauses[] = "p.branch_id = ?";
            $user_branch_id = (int)get_user_branch_id();
            $params[] = $user_branch_id;
            $types .= "i";
        }
        if ($start_date && $end_date) {
            $where_clauses[] = "DATE(p.created_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        $where_sql = !empty($where_clauses) ? " WHERE " . implode(' AND ', $where_clauses) : "";

        // Count total records for pagination
        $count_sql = "SELECT COUNT(p.id) AS total " . $base_sql . $where_sql;
        $stmt_count = $conn->prepare($count_sql);
        safe_bind_param($stmt_count, $types, $params);
        $stmt_count->execute();
        $total_records = (int)$stmt_count->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
        $offset = ($current_page - 1) * $records_per_page;
        $stmt_count->close();

        // Fetch data for the current page
        $data_sql = "SELECT p.*, b.name AS branch_name, TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age " . $base_sql . $where_sql . " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $records_per_page;
        $params[] = $offset;
        $types .= "ii";

        $stmt_data = $conn->prepare($data_sql);
        safe_bind_param($stmt_data, $types, $params);
        $stmt_data->execute();
        $result = $stmt_data->get_result();

        // Build HTML for table rows
        $table_html = '';
        if ($total_records > 0) {
            while ($row = $result->fetch_assoc()) {
                 $table_html .= "<tr>";
                $table_html .= "<td data-label='Name'>" . htmlspecialchars($row['name']) . "</td>";
                $table_html .= "<td data-label='Phone'>" . htmlspecialchars($row['phone']) . "</td>";
                $table_html .= "<td data-label='Age'>" . (int)$row['age'] . "</td>";
                $table_html .= "<td data-label='Registered On'>" . date('d M, Y', strtotime($row['created_at'])) . "</td>";
                if (is_super_admin()) {
                    $table_html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name'] ?? 'N/A') . "</td>";
                }
                $table_html .= "<td data-label='Actions' class='actions'>";
                if (!is_super_admin()) {
                    $table_html .= "<div class='actions-group'>
                                        <a href='view_patient.php?id=" . (int)$row['id'] . "' class='icon-btn' title='View Patient Details'><svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'></path><circle cx='12' cy='12' r='3'></circle></svg></a>
                                        <a href='create_bill.php?step=2&patient_id=" . (int)$row['id'] . "' class='icon-btn' title='Create New Bill'><svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'></path><polyline points='14 2 14 8 20 8'></polyline><line x1='16' y1='13' x2='8' y2='13'></line><line x1='16' y1='17' x2='8' y2='17'></line><polyline points='10 9 9 9 8 9'></polyline></svg></a>
                                    </div>";
                } else {
                    $table_html .= "<a href='view_patient.php?id=" . (int)$row['id'] . "' class='icon-btn' title='View Patient Details'><svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'></path><circle cx='12' cy='12' r='3'></circle></svg></a>";
                }
                $table_html .= "</td></tr>";
            }
        } else {
            $colspan = is_super_admin() ? 6 : 5;
            $table_html = "<tr><td colspan='$colspan' class='text-center'>No patients found.</td></tr>";
        }
        $response['table_html'] = $table_html;
        $stmt_data->close();
        
        // Build HTML for pagination
        $pagination_html = '';
        if ($total_pages > 1) {
            $pagination_html = '<nav><ul class="pagination">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                $pagination_html .= "<li><a href='#' class='page-link $active_class' data-page='$i'>$i</a></li>";
            }
            $pagination_html .= '</ul></nav>';
            $response['pagination_html'] = $pagination_html;
        }
        break;

    // --- INVOICES PAGE LOGIC ---
    case 'invoices':
        $base_sql = "FROM invoices i JOIN patients p ON i.patient_id = p.id LEFT JOIN branches b ON i.branch_id = b.id";
        $where_clauses = ["i.status = ?"];
        $params = [$status];
        $types = "s";

        if (!empty($query)) {
            $where_clauses[] = "(i.invoice_number LIKE ? OR p.name LIKE ?)";
            $search_query = "%" . $query . "%";
            $params[] = $search_query;
            $params[] = $search_query;
            $types .= "ss";
        }
        if (is_super_admin()) {
            if ($branch_id) {
                $where_clauses[] = "i.branch_id = ?";
                $params[] = $branch_id;
                $types .= "i";
            }
        } else {
            $where_clauses[] = "i.branch_id = ?";
            $user_branch_id = (int)get_user_branch_id();
            $params[] = $user_branch_id;
            $types .= "i";
        }
        if ($start_date && $end_date) {
            $where_clauses[] = "DATE(i.created_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        $where_sql = " WHERE " . implode(' AND ', $where_clauses);

        // Count total records
        $count_sql = "SELECT COUNT(i.id) AS total " . $base_sql . $where_sql;
        $stmt_count = $conn->prepare($count_sql);
        safe_bind_param($stmt_count, $types, $params);
        $stmt_count->execute();
        $total_records = (int)$stmt_count->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
        $offset = ($current_page - 1) * $records_per_page;
        $stmt_count->close();

        // Fetch page data
        $data_sql = "SELECT i.*, p.name AS patient_name, b.name AS branch_name " . $base_sql . $where_sql . " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $records_per_page;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt_data = $conn->prepare($data_sql);
        safe_bind_param($stmt_data, $types, $params);
        $stmt_data->execute();
        $result = $stmt_data->get_result();

        // Build HTML for table rows
        $table_html = '';
        if ($total_records > 0) {
            while ($row = $result->fetch_assoc()) {
                $table_html .= "<tr>";
                $table_html .= "<td data-label='Invoice #'>" . htmlspecialchars($row['invoice_number']) . "</td>";
                $table_html .= "<td data-label='Patient'>" . htmlspecialchars($row['patient_name']) . "</td>";
                $table_html .= "<td data-label='Total Amount'>₹" . number_format($row['total_amount'], 2) . "</td>";
                $table_html .= "<td data-label='Amount Paid'>₹" . number_format($row['amount_paid'], 2) . "</td>";
                if ($status == 'Pending') {
                    $table_html .= "<td data-label='Balance Due'>₹" . number_format($row['balance_due'], 2) . "</td>";
                }
                $table_html .= "<td data-label='Date'>" . date('d M, Y', strtotime($row['created_at'])) . "</td>";
                if (is_super_admin()) {
                    $table_html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name'] ?? 'N/A') . "</td>";
                }
                $table_html .= "<td data-label='Actions' class='actions'><div class='actions-group'>";
                if ($status == 'Pending') {
                    $table_html .= "<button class='btn btn-sm btn-primary mark-complete-btn' data-id='" . (int)$row['id'] . "'>Mark as Complete</button>";
                }
                $table_html .= "<a href='" . htmlspecialchars($row['pdf_path']) . "' target='_blank' class='icon-btn' title='View PDF'><svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'></path><polyline points='14 2 14 8 20 8'></polyline><line x1='16' y1='13' x2='8' y2='13'></line><line x1='16' y1='17' x2='8' y2='17'></line><polyline points='10 9 9 9 8 9'></polyline></svg></a>";
                $table_html .= "</div></td></tr>";
            }
        } else {
             $colspan = is_super_admin() ? ($status == 'Pending' ? 8 : 7) : ($status == 'Pending' ? 7 : 6);
             $table_html = "<tr><td colspan='$colspan' class='text-center'>No invoices found.</td></tr>";
        }
        $response['table_html'] = $table_html;
        $stmt_data->close();
        
        // Build HTML for pagination
        $pagination_html = '';
        if ($total_pages > 1) {
            $pagination_html = '<nav><ul class="pagination">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                $pagination_html .= "<li><a href='#' class='page-link $active_class' data-page='$i'>$i</a></li>";
            }
            $pagination_html .= '</ul></nav>';
            $response['pagination_html'] = $pagination_html;
        }
        break;

    // --- TESTS PAGE LOGIC ---
    case 'tests':
        $sql = "SELECT id, test_code, test_name, price, is_outsourced FROM lab_tests WHERE (test_name LIKE ? OR test_code LIKE ?)";
        $params = ["%$query%", "%$query%"];
        $types = "ss";
        $sql .= " ORDER BY test_name ASC";

        $stmt = $conn->prepare($sql);
        safe_bind_param($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $table_html = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $table_html .= "<tr>";
                $table_html .= "<td data-label='Full Name'>" . htmlspecialchars($row['test_name']) . "</td>";
                $table_html .= "<td data-label='Short Name'>" . htmlspecialchars($row['test_code']) . "</td>";
                $table_html .= "<td data-label='Price'>₹" . number_format((float)$row['price'], 2) . "</td>";
                if (is_super_admin()) {
                    $table_html .= "<td data-label='Outsourced'>" . ($row['is_outsourced'] ? 'Yes' : 'No') . "</td>";
                    $table_html .= "<td data-label='Actions' class='actions'>
                                    <div class='actions-group'>
                                    <a href='tests.php?edit=" . (int)$row['id'] . "' class='icon-btn edit' title='Edit'><svg viewBox='0 0 24 24' width='16' height='16' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M12 20h9' /><path d='M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z' /></svg></a>
                                    <a href='tests.php?delete=" . (int)$row['id'] . "' class='icon-btn delete' title='Delete' onclick=\"return confirm('Are you sure you want to delete this test?')\">
                                       <svg viewBox='0 0 24 24' width='16' height='16' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6' /><path d='M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6' /><line x1='10' y1='11' x2='10' y2='17' /><line x1='14' y1='11' x2='14' y2='17' /><path d='M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2' /></svg>
                                    </a>
                                    </div>
                              </td>";
                }
                $table_html .= "</tr>";
            }
        } else {
            $colspan = is_super_admin() ? 5 : 3;
            $table_html = "<tr><td colspan='$colspan' class='text-center'>No tests found.</td></tr>";
        }
        $response['table_html'] = $table_html;
        $stmt->close();
        break;

    // --- USERS PAGE LOGIC ---
    case 'users':
        if (!is_super_admin()) {
            $response['table_html'] = "<tr><td colspan='5' class='text-center'>Access Denied.</td></tr>";
            echo json_encode($response);
            exit;
        }

        $base_sql = "FROM users u LEFT JOIN branches b ON u.branch_id = b.id";
        $where_clauses = ["u.role = 'admin'"];
        $params = [];
        $types = "";

        if (!empty($query)) {
            $where_clauses[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $search_query = "%" . $query . "%";
            $params[] = $search_query;
            $params[] = $search_query;
            $params[] = $search_query;
            $types .= "sss";
        }
        if ($branch_id) {
            $where_clauses[] = "u.branch_id = ?";
            $params[] = $branch_id;
            $types .= "i";
        }
        $where_sql = " WHERE " . implode(' AND ', $where_clauses);
        
        $sql = "SELECT u.id, u.full_name, u.username, u.email, b.name AS branch_name " . $base_sql . $where_sql . " ORDER BY u.full_name ASC";
        
        $stmt = $conn->prepare($sql);
        safe_bind_param($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $table_html = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $table_html .= "<tr>";
                $table_html .= "<td data-label='Name'>" . htmlspecialchars($row['full_name']) . "</td>";
                $table_html .= "<td data-label='Phone (Username)'>" . htmlspecialchars($row['username']) . "</td>";
                $table_html .= "<td data-label='Email'>" . htmlspecialchars($row['email']) . "</td>";
                $table_html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name'] ?? 'N/A') . "</td>";
                $table_html .= "<td data-label='Actions' class='actions'>
                            <div class='actions-group'>
                                <a href='manage_user.php?id=" . (int)$row['id'] . "' class='icon-btn edit' title='Edit'><svg viewBox='0 0 24 24' width='16' height='16' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M12 20h9' /><path d='M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z' /></svg></a>
                                <a href='users.php?delete=" . (int)$row['id'] . "' class='icon-btn delete' title='Delete' onclick=\"return confirm('Are you sure you want to delete this admin?')\">
                                    <svg viewBox='0 0 24 24' width='16' height='16' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6' /><path d='M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6' /><line x1='10' y1='11' x2='10' y2='17' /><line x1='14' y1='11' x2='14' y2='17' /><path d='M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2' /></svg>
                                </a>
                            </div>
                          </td>";
                $table_html .= "</tr>";
            }
        } else {
            $table_html = "<tr><td colspan='5' class='text-center'>No admin users found.</td></tr>";
        }
        $response['table_html'] = $table_html;
        $stmt->close();
        break;
}

echo json_encode($response);
?>


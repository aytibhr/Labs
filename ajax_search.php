<?php
require_once "includes/functions.php";
redirect_if_not_logged_in();

if (!isset($_GET['page']) || !isset($_GET['query'])) {
    exit('Invalid request');
}

$page      = $_GET['page'];
$query_raw = $_GET['query'];
$query     = $conn->real_escape_string($query_raw);
$branch_id = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;

$html = '';

switch ($page) {
    case 'patients':
        // Super admin can see all; admins limited to their branch
        $sql = "SELECT p.*, b.name AS branch_name,
                       TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age_years
                FROM patients p
                JOIN branches b ON p.branch_id = b.id
                WHERE (p.name LIKE '%$query%' OR p.phone LIKE '%$query%')";
        if (is_super_admin()) {
            if ($branch_id) $sql .= " AND p.branch_id = $branch_id";
        } else {
            $sql .= " AND p.branch_id = " . (int)get_user_branch_id();
        }
        $sql .= " ORDER BY p.name ASC";

        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $html .= "<tr>";
                $html .= "<td data-label='Name'>" . htmlspecialchars($row['name']) . "</td>";
                $html .= "<td data-label='Phone'>" . htmlspecialchars($row['phone']) . "</td>";
                $html .= "<td data-label='Age'>" . (int)$row['age_years'] . "</td>";
                if (is_super_admin()) {
                    $html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name']) . "</td>";
                }
                $html .= "<td data-label='Actions' class='actions'>
                            <a class='btn-link' href='create_bill.php?step=2&patient_id=" . (int)$row['id'] . "'>New Bill</a>
                          </td>";
                $html .= "</tr>";
            }
        }
        break;

    case 'invoices':
        $sql = "SELECT i.id, i.invoice_number, i.total_amount, i.created_at,
                       p.name AS patient_name, b.name AS branch_name
                FROM invoices i
                JOIN patients p ON i.patient_id = p.id
                JOIN branches b ON i.branch_id = b.id
                WHERE i.is_deleted = 0
                  AND (i.invoice_number LIKE '%$query%' OR p.name LIKE '%$query%')";
        if (is_super_admin()) {
            if ($branch_id) $sql .= " AND i.branch_id = $branch_id";
        } else {
            $sql .= " AND i.branch_id = " . (int)get_user_branch_id();
        }
        $sql .= " ORDER BY i.created_at DESC";

        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $html .= "<tr>";
                $html .= "<td data-label='Invoice #'>" . htmlspecialchars($row['invoice_number']) . "</td>";
                $html .= "<td data-label='Patient'>" . htmlspecialchars($row['patient_name']) . "</td>";
                $html .= "<td data-label='Amount'>₹" . number_format((float)$row['total_amount'], 2) . "</td>";
                $html .= "<td data-label='Date'>" . htmlspecialchars(date('d M Y', strtotime($row['created_at']))) . "</td>";
                if (is_super_admin()) {
                    $html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name']) . "</td>";
                }
                $html .= "<td data-label='Actions' class='actions'>
                            <a class='btn-link' href='success.php?invoice_id=" . (int)$row['id'] . "' title='View'>View</a>
                          </td>";
                $html .= "</tr>";
            }
        }
        break;

    case 'tests':
        $sql = "SELECT t.id, t.test_code, t.test_name, t.price, b.name AS branch_name
                FROM lab_tests t
                JOIN branches b ON t.branch_id = b.id
                WHERE (t.test_name LIKE '%$query%' OR t.test_code LIKE '%$query%')";
        if (is_super_admin()) {
            if ($branch_id) $sql .= " AND t.branch_id = $branch_id";
        } else {
            $sql .= " AND t.branch_id = " . (int)get_user_branch_id();
        }
        $sql .= " ORDER BY t.test_name ASC";

        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $html .= "<tr>";
                $html .= "<td data-label='Test Name'>" . htmlspecialchars($row['test_name']) . "</td>";
                $html .= "<td data-label='Code'>" . htmlspecialchars($row['test_code']) . "</td>";
                $html .= "<td data-label='Price'>₹" . number_format((float)$row['price'], 2) . "</td>";
                if (is_super_admin()) {
                    $html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name']) . "</td>";
                    $html .= "<td data-label='Actions' class='actions'>
                                <a class='btn-link' href='tests.php?edit=" . (int)$row['id'] . "' title='Edit'><i class='la la-edit'></i></a>
                                <a class='btn-link-danger' href='tests.php?delete=" . (int)$row['id'] . "' onclick=\"return confirm('Are you sure you want to delete this test?');\" title='Delete'><i class='la la-trash'></i></a>
                              </td>";
                }
                $html .= "</tr>";
            }
        }
        break;

    case 'users':
        // Only super admin can list/search admins
        if (!is_super_admin()) { exit; }

        $sql = "SELECT u.id, u.full_name, u.username, b.name AS branch_name
                FROM users u
                JOIN branches b ON u.branch_id = b.id
                WHERE u.role = 'admin'
                  AND (u.full_name LIKE '%$query%' OR u.username LIKE '%$query%')";
        if ($branch_id) $sql .= " AND u.branch_id = $branch_id";
        $sql .= " ORDER BY u.full_name ASC";

        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $html .= "<tr>";
                $html .= "<td data-label='Name'>" . htmlspecialchars($row['full_name']) . "</td>";
                $html .= "<td data-label='Username'>" . htmlspecialchars($row['username']) . "</td>";
                $html .= "<td data-label='Branch'>" . htmlspecialchars($row['branch_name']) . "</td>";
                $html .= "<td data-label='Actions' class='actions'>
                            <a class='btn-link' href='manage_user.php?edit=" . (int)$row['id'] . "' title='Edit'><i class='la la-edit'></i></a>
                            <form method='POST' action='users.php' onsubmit=\"return confirm('Delete this admin?')\" style='display:inline;'>
                                <input type='hidden' name='user_id' value='" . (int)$row['id'] . "'>
                                <button type='submit' name='delete_user' class='btn-link-danger' title='Delete'><i class='la la-trash'></i></button>
                            </form>
                          </td>";
                $html .= "</tr>";
            }
        }
        break;

    default:
        // Unsupported page
        exit;
}

echo $html;

<?php
session_start();
require_once 'db.php';

function is_logged_in() {
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

function is_super_admin() {
    return is_logged_in() && isset($_SESSION["role"]) && $_SESSION["role"] === 'superadmin';
}

function get_user_branch_id() {
    return $_SESSION["branch_id"] ?? null;
}

function get_user_id() {
    return $_SESSION["id"] ?? null;
}

function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        header("location: login.php");
        exit;
    }
}

function generate_invoice_number() {
    global $conn;
    
    // Get the highest numerical invoice number from the database
    $query = "SELECT MAX(CAST(invoice_number AS UNSIGNED)) as max_num FROM invoices";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $next_num = ($row['max_num'] ?? 0) + 1;
    
    // Format the number with leading zeros (e.g., 0001, 0002)
    return str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

function get_branch_name($branch_id) {
    global $conn;
    if ($branch_id === null) return 'All Branches';
    $stmt = $conn->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $branch = $result->fetch_assoc();
    $stmt->close();
    return $branch ? $branch['name'] : 'Unknown Branch';
}

function get_setting($key) {
    global $conn;
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    return $settings[$key] ?? null;
}
?>

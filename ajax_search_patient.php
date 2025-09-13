<?php
require_once "includes/functions.php";
header('Content-Type: application/json');

if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $branch_id = get_user_branch_id();
    $sql_branch_condition = is_super_admin() ? "" : "AND branch_id = $branch_id";

    $sql = "SELECT id, name, phone, dob FROM patients WHERE (name LIKE '%$query%' OR phone LIKE '%$query%') $sql_branch_condition LIMIT 10";
    
    $result = $conn->query($sql);
    $patients = [];
    while($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    echo json_encode($patients);
}
?>
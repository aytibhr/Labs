<?php
require_once "includes/functions.php";
redirect_if_not_logged_in();

$query_raw = $_GET['query'] ?? '';
$query = $conn->real_escape_string($query_raw);
$html = '';

// Only proceed if a search query is provided
if (!empty($query)) {
    // Base SQL statement (no longer uses branch_id)
    $sql = "SELECT id, test_code, test_name, price 
            FROM lab_tests 
            WHERE (test_name LIKE ? OR test_code LIKE ?)
            LIMIT 15";
    
    $stmt = $conn->prepare($sql);
    $search_query = "%" . $query . "%";
    $stmt->bind_param("ss", $search_query, $search_query);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Build the list item for each test found
            $html .= "<li data-id='" . (int)$row['id'] . "' 
                          data-name='" . htmlspecialchars($row['test_name'], ENT_QUOTES) . "' 
                          data-code='" . htmlspecialchars($row['test_code'], ENT_QUOTES) . "' 
                          data-price='" . (float)$row['price'] . "'>";
            $html .= "<strong>" . htmlspecialchars($row['test_name']) . "</strong>";
            $html .= "<span>" . htmlspecialchars($row['test_code']) . " - ₹" . number_format($row['price'], 2) . "</span>";
            $html .= "</li>";
        }
    } else {
        $html = '<li class="no-items">No tests found for your search.</li>';
    }
    $stmt->close();
}

// Return the HTML (or an empty string if no query was provided)
echo $html;
?>


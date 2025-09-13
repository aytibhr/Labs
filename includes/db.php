<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u851719866_admin');
define('DB_PASSWORD', '+5Y[En$Yn7');
define('DB_NAME', 'u851719866_lab_new');

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>
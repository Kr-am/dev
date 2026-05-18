<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Default XAMPP user
// If your MySQL root account has a password, set it here.
define('DB_PASS', '');
define('DB_NAME', 'trackmybite');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . ". Please update config.php with the correct MySQL password for root.");
}

// Start session
session_start();
?>
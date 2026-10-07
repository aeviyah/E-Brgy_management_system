<?php
// Start session para sa Role-Based Access Control
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";       // Default XAMPP user
$pass = "";           // Default XAMPP password (blank)
$dbname = "e_brgy_db";

// Gumawa ng MySQLi Connection
$conn = new mysqli($host, $user, $pass, $dbname);

// I-check ang connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// I-set ang Charset sa UTF-8
$conn->set_charset("utf8mb4");
?>
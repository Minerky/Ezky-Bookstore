<?php
// db.php - Database connection using require_once

$host = "localhost";
$user = "root";
$password = "";
$database = "bookstore";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

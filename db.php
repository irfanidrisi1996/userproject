<?php
$host = 'localhost';
$db   = 'user_management'; // your database name
$user = 'root';            // default XAMPP user
$pass = '';                // default XAMPP password is empty

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?> 
<?php
// addme.php

// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'svcc_library';

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Admin user details
$admin_username = 'MasterFontyo';
$admin_password = password_hash('Fontiti000', PASSWORD_DEFAULT);

// Check if admin already exists
$sql = "SELECT user_id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Admin user already exists.";
} else {
    // Insert admin user
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $admin_username, $admin_password);

    if ($stmt->execute()) {
        echo "Admin user added successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }
}

$stmt->close();
$conn->close();
?>
<?php
require 'dbconnection.php';

// Create default user
$user_name = "Test User";
$user_email = "user@bracu.ac.bd";
$user_password = password_hash("user123", PASSWORD_DEFAULT);

$user_stmt = $conn->prepare("INSERT INTO User (Name, Email, Password) VALUES (?, ?, ?)");
if ($user_stmt) {
    $user_stmt->bind_param("sss", $user_name, $user_email, $user_password);
    $user_stmt->execute();
    echo "Default user inserted successfully.<br>";
} else {
    echo "User insert prepare failed: " . $conn->error . "<br>";
}

// Create default admin
$admin_name = "Admin";
$admin_email = "admin@bracu.ac.bd";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);

$admin_stmt = $conn->prepare("INSERT INTO Admin (Name, Email, Password) VALUES (?, ?, ?)");
if ($admin_stmt) {
    $admin_stmt->bind_param("sss", $admin_name, $admin_email, $admin_password);
    $admin_stmt->execute();
    echo "Default admin inserted successfully.";
} else {
    echo "Admin insert prepare failed: " . $conn->error;
}

$conn->close();
?>


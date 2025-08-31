<?php
require 'dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = $_POST['admin_id'];

    $stmt = $conn->prepare("INSERT INTO Admin (Admin_ID) VALUES (?)");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    echo "Admin added successfully!";
}
?>

<form method="POST">
    <h2>Create Admin</h2>
    Admin User ID (must exist in User table): <input type="number" name="admin_id" required><br><br>
    <input type="submit" value="Add Admin">
</form>

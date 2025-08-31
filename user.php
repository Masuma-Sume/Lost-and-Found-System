<?php
require 'dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $contact = $_POST['contact'];

    $stmt = $conn->prepare("INSERT INTO User (Name, Contact_info) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $contact);
    $stmt->execute();
    echo "User added successfully!";
}
?>

<form method="POST">
    <h2>Create User</h2>
    Name: <input type="text" name="name" required><br><br>
    Contact Info: <input type="text" name="contact" required><br><br>
    <input type="submit" value="Add User">
</form>
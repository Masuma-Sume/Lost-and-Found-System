<?php
require 'dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $type = $_POST['reward_type'];
    $points = $_POST['points'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO Reward (User_ID, Reward_type, Points, Reward_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $type, $points, $date);
    
    if ($stmt->execute()) {
        echo "Reward added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<form method="POST">
    <h2>Assign Reward</h2>
    User ID: <input type="number" name="user_id" required><br><br>
    Reward Type: <input type="text" name="reward_type" required><br><br>
    Points: <input type="number" name="points" required><br><br>
    Reward Date: <input type="date" name="date" required><br><br>
    <input type="submit" value="Add Reward">
</form>

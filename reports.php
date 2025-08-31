<?php
require 'dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = $_POST['item_id'];
    $user_id = $_POST['user_id'];

    $stmt = $conn->prepare("INSERT INTO Reported_By (Item_ID, User_ID) VALUES (?, ?)");
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    echo "Report added successfully!";
}
?>

<form method="POST">
    <h2>Link Report</h2>
    Item ID: <input type="number" name="item_id" required><br><br>
    User ID: <input type="number" name="user_id" required><br><br>
    <input type="submit" value="Link Report">
</form>

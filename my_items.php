<?php
session_start();
require 'dbconnection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Enable error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reported Items</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 20px; }
        h2 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f9f9f9; }
        .status-lost { color: #e74c3c; }
        .status-found { color: #2ecc71; }
        .status-claimed { color: #3498db; }
    </style>
</head>
<body>
    <h2>My Reported Items</h2>
    <?php
    $sql = $conn->prepare("SELECT i.Item_ID, i.Name, i.Description, i.Logged_date, i.Item_status
                           FROM Item i
                           INNER JOIN Reported_By rb ON i.Item_ID = rb.Item_ID
                           WHERE rb.User_ID = ?
                           ORDER BY i.Logged_date DESC");
    $sql->bind_param("i", $user_id);
    $sql->execute();
    $result = $sql->get_result();

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Date</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $status = htmlspecialchars($row["Item_status"]);
            if (!in_array($status, ['lost', 'found', 'claimed'])) $status = 'unknown';

            echo "<tr>";
            echo "<td>" . $row["Item_ID"] . "</td>";
            echo "<td>" . htmlspecialchars($row["Name"]) . "</td>";
            echo "<td>" . htmlspecialchars($row["Description"]) . "</td>";
            echo "<td>" . date('d M Y H:i', strtotime($row["Logged_date"])) . "</td>";
            echo "<td><span class='status-{$status}'>" . ucfirst($status) . "</span></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>You have not reported any items yet.</p>";
    }
    ?>
</body>
</html>

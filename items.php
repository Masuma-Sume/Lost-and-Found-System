<?php
session_start();
require 'dbconnection.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$allowed_statuses = ['lost', 'found', 'claimed'];
$filter_clause = in_array($status_filter, $allowed_statuses) ? "WHERE i.Item_status = '$status_filter'" : "";

$sql = "SELECT i.Item_ID, i.Name AS ItemName, i.Description, i.Item_status, i.Logged_date, u.Name AS Reporter
        FROM Item i
        LEFT JOIN Reported_By rb ON i.Item_ID = rb.Item_ID
        LEFT JOIN User u ON rb.User_ID = u.User_ID
        $filter_clause
        ORDER BY i.Logged_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Items</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ccc;
        }
        th {
            background: #f2f2f2;
        }
        .filter-buttons a {
            margin-right: 10px;
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ccc;
            background: #eee;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Items - Lost & Found</h1>

    <div class="filter-buttons">
        <a href="items.php">All</a>
        <a href="items.php?status=lost">Lost</a>
        <a href="items.php?status=found">Found</a>
        <a href="items.php?status=claimed">Claimed</a>
    </div>

    <?php
    if ($result && $result->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Description</th><th>Status</th><th>Reporter</th><th>Logged Date</th></tr>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['Item_ID'] . '</td>';
            echo '<td>' . htmlspecialchars($row['ItemName']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Description']) . '</td>';
            echo '<td>' . ucfirst($row['Item_status']) . '</td>';
            echo '<td>' . htmlspecialchars($row['Reporter']) . '</td>';
            echo '<td>' . date('d M Y H:i', strtotime($row['Logged_date'])) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No items found.</p>';
    }
    ?>
</body>
</html>

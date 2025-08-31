<?php
session_start();
require 'dbconnection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signing.php');
    exit();
}

// Check if user is admin
$is_admin = false;
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT 1 FROM Admin WHERE Admin_ID = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$is_admin = $stmt->get_result()->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BRAC Lost & Found Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
        }
        .topbar {
            background: #343a40;
            padding: 15px;
            color: white;
            display: flex;
            justify-content: space-between;
        }
        .topbar .nav-links a {
            margin-left: 15px;
            color: white;
            text-decoration: none;
        }
        .container {
            padding: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        .status-lost { color: #e74c3c; }
        .status-found { color: #2ecc71; }
        .status-claimed { color: #3498db; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="logo">BRAC Lost & Found</div>
        <div class="nav-links">
            <a href="dashboard.php">Home</a>
            <a href="items.php">Items</a>
            <?php if ($is_admin): ?>
                <a href="admin.php">Admin Panel</a>
                <a href="reports.php">Reports</a>
            <?php else: ?>
                <a href="my_items.php">My Items</a>
                <a href="rewards.php">Rewards</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>Recently Lost & Found Items</h2>
            <?php
            $sql = "SELECT i.Item_ID, i.Name AS ItemName, i.Logged_date, i.Description, i.Item_status, u.Name AS ReporterName
                    FROM Item i
                    LEFT JOIN Reported_By rb ON i.Item_ID = rb.Item_ID
                    LEFT JOIN User u ON rb.User_ID = u.User_ID
                    ORDER BY i.Logged_date DESC LIMIT 10";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                echo '<table><tr><th>ID</th><th>Item Name</th><th>Description</th><th>Reporter</th><th>Date</th><th>Status</th></tr>';
                while ($row = $result->fetch_assoc()) {
                    $status = in_array($row["Item_status"], ['lost', 'found', 'claimed']) ? $row["Item_status"] : 'unknown';
                    echo "<tr>
                        <td>{$row['Item_ID']}</td>
                        <td>" . htmlspecialchars($row['ItemName']) . "</td>
                        <td>" . htmlspecialchars($row['Description']) . "</td>
                        <td>" . htmlspecialchars($row['ReporterName']) . "</td>
                        <td>" . date('d M Y H:i', strtotime($row['Logged_date'])) . "</td>
                        <td class='status-{$status}'>" . ucfirst($status) . "</td>
                    </tr>";
                }
                echo '</table>';
            } else {
                echo '<p>No items found.</p>';
            }
            ?>
        </div>

        <?php if ($is_admin): ?>
        <div class="card">
            <h2>Admin Statistics</h2>
            <?php
            $stats_sql = "SELECT 
                (SELECT COUNT(*) FROM Item WHERE Item_status = 'lost') AS lost_items,
                (SELECT COUNT(*) FROM Item WHERE Item_status = 'found') AS found_items,
                (SELECT COUNT(*) FROM Item WHERE Item_status = 'claimed') AS claimed_items,
                (SELECT COUNT(*) FROM User) AS total_users";
            $stats = $conn->query($stats_sql)->fetch_assoc();
            ?>
            <div style="display: flex; gap: 20px;">
                <div><h3>Lost Items</h3><p><?= $stats['lost_items'] ?></p></div>
                <div><h3>Found Items</h3><p><?= $stats['found_items'] ?></p></div>
                <div><h3>Claimed Items</h3><p><?= $stats['claimed_items'] ?></p></div>
                <div><h3>Total Users</h3><p><?= $stats['total_users'] ?></p></div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <h2>Your Recent Activity</h2>
            <?php
            $stmt = $conn->prepare("SELECT i.Item_ID, i.Name AS ItemName, i.Logged_date, i.Item_status
                                    FROM Item i
                                    JOIN Reported_By rb ON i.Item_ID = rb.Item_ID
                                    WHERE rb.User_ID = ?
                                    ORDER BY i.Logged_date DESC
                                    LIMIT 5");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo '<table><tr><th>ID</th><th>Item</th><th>Date</th><th>Status</th></tr>';
                while ($row = $result->fetch_assoc()) {
                    $status = in_array($row["Item_status"], ['lost', 'found', 'claimed']) ? $row["Item_status"] : 'unknown';
                    echo "<tr>
                        <td>{$row['Item_ID']}</td>
                        <td>" . htmlspecialchars($row['ItemName']) . "</td>
                        <td>" . date('d M Y H:i', strtotime($row['Logged_date'])) . "</td>
                        <td class='status-{$status}'>" . ucfirst($status) . "</td>
                    </tr>";
                }
                echo '</table>';
            } else {
                echo "<p>No recent activity.</p>";
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

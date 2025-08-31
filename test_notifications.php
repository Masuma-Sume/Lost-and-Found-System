<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Add a test notification if requested
if (isset($_GET['add_test'])) {
    try {
        $test_message = "This is a test notification to verify the system is working properly.";
        $sql = "INSERT INTO notifications (User_ID, Type, Message) VALUES (?, 'system', ?)";
        executeQuery($conn, $sql, [$user_id, $test_message], 'ss');
        echo "Test notification added successfully!<br>";
    } catch (Exception $e) {
        echo "Error adding test notification: " . $e->getMessage() . "<br>";
    }
}

// Get current notifications
try {
    $sql = "SELECT * FROM notifications WHERE User_ID = ? ORDER BY Created_At DESC";
    $stmt = executeQuery($conn, $sql, [$user_id], 's');
    $notifications = fetchAll($stmt);
    
    echo "<h2>Current Notifications for User: $user_id</h2>";
    echo "<p><a href='?add_test=1'>Add Test Notification</a></p>";
    echo "<p><a href='notifications.php'>Go to Notifications Page</a></p>";
    
    if (empty($notifications)) {
        echo "<p>No notifications found.</p>";
    } else {
        echo "<ul>";
        foreach ($notifications as $notification) {
            echo "<li>";
            echo "ID: " . $notification['Notification_ID'] . "<br>";
            echo "Type: " . $notification['Type'] . "<br>";
            echo "Message: " . htmlspecialchars($notification['Message']) . "<br>";
            echo "Read: " . ($notification['Is_Read'] ? 'Yes' : 'No') . "<br>";
            echo "Created: " . $notification['Created_At'] . "<br>";
            echo "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "Error fetching notifications: " . $e->getMessage();
}
?> 
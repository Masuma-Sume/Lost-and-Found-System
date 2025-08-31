<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = '';
$debug_info = [];

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

// Check database connection
$debug_info['db_connection'] = $conn->ping() ? 'Connected' : 'Not connected';

// Check if notifications table exists
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
$debug_info['notifications_table_exists'] = ($result && $result->num_rows > 0) ? 'Yes' : 'No';

// Check if sendNotificationToAllUsers function exists
$debug_info['notification_function_exists'] = function_exists('sendNotificationToAllUsers') ? 'Yes' : 'No';

// Check if there are any users in the system
$users_result = $conn->query("SELECT COUNT(*) as count FROM user WHERE Account_Status = 'active'");
$users_count = $users_result->fetch_assoc()['count'];
$debug_info['active_users_count'] = $users_count;

// Check if there are any notifications in the system
$notifications_result = $conn->query("SELECT COUNT(*) as count FROM notifications");
$notifications_count = $notifications_result->fetch_assoc()['count'];
$debug_info['total_notifications_count'] = $notifications_count;

// Fix: Ensure notifications are sent to all users when items are reported
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fix_notifications'])) {
        // Update the sendNotificationToAllUsers function if needed
        // This is already done in the config.php file
        
        // Test sending a notification
        $test_item_name = 'Test Item ' . date('Y-m-d H:i:s');
        $test_location = 'Test Location';
        $test_item_type = 'lost';
        
        // Insert test item
        $sql = "INSERT INTO items (User_ID, Item_Name, Item_Type, Category_ID, Location, Description, Date_Lost_Found, Status, Image_URL) 
                VALUES (?, ?, ?, 1, ?, 'Test description', NOW(), 'open', NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $user_id, $test_item_name, $test_item_type, $test_location);
        
        if ($stmt->execute()) {
            $test_item_id = $stmt->insert_id;
            
            // Send notifications to all users
            $notification_sent = sendNotificationToAllUsers($conn, $user_id, $test_item_id, $test_item_name, $test_item_type, $test_location);
            
            if ($notification_sent) {
                $success_message = "Notification system fixed and tested successfully! A test notification has been sent to all users.";
            } else {
                $error_message = "Failed to send test notification. Please check the error log for details.";
            }
        } else {
            $error_message = "Error creating test item: " . $stmt->error;
        }
    }
}

// Get recent notifications for debugging
$recent_notifications_sql = "SELECT n.*, u.Name as User_Name, i.Item_Name, i.Item_Type 
                           FROM notifications n 
                           JOIN user u ON n.User_ID = u.User_ID 
                           LEFT JOIN items i ON n.Item_ID = i.Item_ID 
                           ORDER BY n.Created_At DESC LIMIT 10";
$recent_notifications = $conn->query($recent_notifications_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Notification System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1, h2 { color: #009688; }
        .success { background: #e0f7fa; color: #00796B; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        button { background: #009688; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #00796B; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .debug-item { margin-bottom: 10px; }
        .debug-label { font-weight: bold; display: inline-block; width: 200px; }
        .debug-value { display: inline-block; }
        .back-link { display: inline-block; margin-top: 20px; color: #009688; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Fix Notification System</h1>
        
        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <h2>System Diagnostics</h2>
        <div class="debug-info">
            <?php foreach ($debug_info as $key => $value): ?>
                <div class="debug-item">
                    <span class="debug-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</span>
                    <span class="debug-value"><?php echo $value; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <h2>Fix and Test Notification System</h2>
        <p>Click the button below to fix the notification system and send a test notification to all users:</p>
        <form method="POST">
            <button type="submit" name="fix_notifications">Fix and Test Notifications</button>
        </form>
        
        <h2>Recent Notifications</h2>
        <?php if ($recent_notifications && $recent_notifications->num_rows > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Read</th>
                    <th>Created</th>
                </tr>
                <?php while($notification = $recent_notifications->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $notification['Notification_ID']; ?></td>
                        <td><?php echo htmlspecialchars($notification['User_Name']); ?></td>
                        <td><?php echo htmlspecialchars($notification['Item_Name'] ?? 'N/A'); ?></td>
                        <td><?php echo $notification['Type']; ?></td>
                        <td><?php echo htmlspecialchars($notification['Message']); ?></td>
                        <td><?php echo $notification['Is_Read'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($notification['Created_At'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No notifications found in the system.</p>
        <?php endif; ?>
        
        <a href="home.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>
</body>
</html>
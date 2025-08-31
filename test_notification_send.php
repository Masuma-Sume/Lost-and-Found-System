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

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

// Test sending a notification to all users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_notification'])) {
    // Create a test item
    $item_name = 'Test Item ' . date('Y-m-d H:i:s');
    $location = 'Test Location';
    $item_type = 'lost'; // or 'found'
    
    // Insert test item
    $sql = "INSERT INTO items (User_ID, Item_Name, Item_Type, Category_ID, Location, Description, Date_Lost_Found, Status, Image_URL) 
            VALUES (?, ?, ?, 1, ?, 'Test description', NOW(), 'open', NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $user_id, $item_name, $item_type, $location);
    
    if ($stmt->execute()) {
        $item_id = $stmt->insert_id;
        
        // Send notifications to all users
        $notification_sent = sendNotificationToAllUsers($conn, $user_id, $item_id, $item_name, $item_type, $location);
        
        if ($notification_sent) {
            $success_message = "Test notification sent successfully! Check the notifications page to verify.";
        } else {
            $error_message = "Failed to send test notification. Check error log for details.";
        }
    } else {
        $error_message = "Error creating test item: " . $stmt->error;
    }
}

// Get all notifications for debugging
$all_notifications_sql = "SELECT n.*, u.Name as User_Name, i.Item_Name 
                         FROM notifications n 
                         JOIN user u ON n.User_ID = u.User_ID 
                         LEFT JOIN items i ON n.Item_ID = i.Item_ID 
                         ORDER BY n.Created_At DESC LIMIT 20";
$all_notifications = $conn->query($all_notifications_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Notification System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #009688; }
        .success { background: #e0f7fa; color: #00796B; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        button { background: #009688; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #00796B; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .back-link { display: inline-block; margin-top: 20px; color: #009688; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Notification System</h1>
        
        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <p>Click the button below to create a test item and send notifications to all users:</p>
            <button type="submit" name="test_notification">Send Test Notification</button>
        </form>
        
        <h2>Recent Notifications (Debug Info)</h2>
        <?php if ($all_notifications && $all_notifications->num_rows > 0): ?>
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
                <?php while($notification = $all_notifications->fetch_assoc()): ?>
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
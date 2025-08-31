<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$notifications = [];

try {
    // Mark notifications as read
    if (isset($_GET['mark_read'])) {
        markNotificationsAsRead($conn, $user_id);
        header("Location: notifications.php");
        exit();
    }

    // Get notifications
    $notifications_sql = "SELECT n.*, i.Item_Name, i.Item_Type 
                         FROM notifications n
                         LEFT JOIN items i ON n.Item_ID = i.Item_ID
                         WHERE n.User_ID = ?
                         ORDER BY n.Created_At DESC";
    $stmt = executeQuery($conn, $notifications_sql, [$user_id], 's');
    $notifications = fetchAll($stmt);

} catch (Exception $e) {
    error_log("Notifications error: " . $e->getMessage());
    $error_message = "An error occurred while fetching notifications. Please try again later.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Notifications</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #009688; /* Teal */
            --primary-dark: #00796B; /* Dark Teal */
            --secondary-color: #ffffff;
            --text-color: #222;
            --text-light: #666;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Merriweather', serif;
        }
        body {
            background: #f5f5f5;
            color: var(--text-color);
            line-height: 1.6;
        }
        .top-bar {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-menu {
            display: flex;
            gap: 1rem;
        }
        .nav-item {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: background-color 0.3s;
        }
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .notifications-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
        }
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .mark-read {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
        }
        .mark-read:hover {
            text-decoration: underline;
        }
        .notification-list {
            list-style: none;
        }
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            transition: background-color 0.3s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            background-color: #e0f2f1;
        }
        .notification-item.unread:hover {
            background-color: #b2dfdb;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            background: #e0f7fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }
        .notification-content {
            flex: 1;
        }
        .notification-title {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .notification-message {
            color: #666;
            font-size: 0.9rem;
        }
        .notification-time {
            color: #999;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .no-notifications {
            text-align: center;
            color: #666;
            padding: 2rem;
        }
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            margin: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .notification-type-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background-color: #e0f7fa;
            color: var(--primary-color);
            display: inline-block;
            margin-bottom: 5px;
            font-weight: 700;
        }
        .notification-type-badge.claim {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .notification-type-badge.match {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.3s;
            background-color: var(--primary-color);
            color: white;
            font-weight: 700;
            border: none;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-primary:hover, .btn:hover {
            background-color: var(--primary-dark);
        }

    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo"><i class="fas fa-tree"></i> BRAC UNIVERSITY LOST & FOUND</div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="my_reports.php" class="nav-item">
                <i class="fas fa-list"></i> My Reports
            </a>
            <a href="my_claims.php" class="nav-item">
                <i class="fas fa-hand-paper"></i> My Claims
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <a href="report_found.php" class="nav-item">
                <i class="fas fa-plus-circle"></i> Report Found Item
            </a>
        </div>
    </div>

    <div class="container">
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <div class="notifications-card">
                <div class="notifications-header">
                    <h2>Notifications</h2>
                    <?php if ($notifications && count($notifications) > 0): ?>
                        <a href="?mark_read=1" class="mark-read">
                            <i class="fas fa-check-double"></i> Mark all as read
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($notifications && count($notifications) > 0): ?>
                    <ul class="notification-list">
                        <?php foreach($notifications as $notification): ?>
                            <li class="notification-item <?php echo $notification['Is_Read'] ? '' : 'unread'; ?>">
                                <div class="notification-icon">
                                    <?php if ($notification['Type'] === 'claim'): ?>
                                        <i class="fas fa-hand-holding"></i>
                                    <?php elseif ($notification['Type'] === 'match'): ?>
                                        <i class="fas fa-link"></i>
                                    <?php else: ?>
                                        <i class="fas fa-bell"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-content">
                                    <span class="notification-type-badge <?php echo $notification['Type']; ?>">
                                        <?php
                                        switch($notification['Type']) {
                                            case 'claim':
                                                echo "Claim Request";
                                                break;
                                            case 'match':
                                                echo "Potential Match";
                                                break;
                                            default:
                                                echo "General";
                                        }
                                        ?>
                                    </span>
                                    <h3 class="notification-title">
                                        <?php echo htmlspecialchars($notification['Message']); ?>
                                    </h3>
                                    <?php if ($notification['Item_Name']): ?>
                                        <p class="notification-message">
                                            Related item: <?php echo htmlspecialchars($notification['Item_Name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="notification-actions">
                                        <?php if ($notification['Item_ID']): ?>
                                            <a href="view_item.php?id=<?php echo $notification['Item_ID']; ?>" class="btn btn-primary">
                                                <i class="fas fa-eye"></i> View Item
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y h:i A', strtotime($notification['Created_At'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-notifications">You have no notifications.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
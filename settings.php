<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Settings - BRAC UNIVERSITY LOST & FOUND</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: 'Merriweather', serif; background: #f5f5f5; margin: 0; padding: 0; }
        .top-bar { background-color: #009688; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .logo { font-size: 1.5rem; font-weight: 700; }
        .nav-menu { display: flex; gap: 1.5rem; align-items: center; }
        .nav-item { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 50px; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .nav-item:hover { background-color: rgba(255,255,255,0.1); }
        .notification-badge { background-color: #ff5722; color: white; border-radius: 50%; padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; font-weight: bold; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #009688; text-align: center; }
        .coming-soon { text-align: center; color: #666; margin-top: 40px; font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">BRAC UNIVERSITY LOST & FOUND</div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item"><i class="fas fa-home"></i> Home</a>
            <a href="my_reports.php" class="nav-item"><i class="fas fa-list"></i> My Reports</a>
            <a href="my_claims.php" class="nav-item"><i class="fas fa-hand-paper"></i> My Claims</a>
            <a href="notifications.php" class="nav-item">
                <i class="fas fa-bell"></i> Notifications
                <?php if (isset($notifications) && $notifications > 0): ?>
                    <span class="notification-badge"><?php echo $notifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="rewards.php" class="nav-item"><i class="fas fa-trophy"></i> Rewards</a>
            <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="container">
        <h1>Settings</h1>
        <div class="coming-soon">
            Settings features coming soon!<br>
        </div>
    </div>
</body>
</html>
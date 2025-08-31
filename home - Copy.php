<?php

include 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    // Get user information using prepared statement
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT User_ID, Email, Name, Contact_No 
            FROM user 
            WHERE User_ID = ?";
    
    $stmt = executeQuery($conn, $sql, [$user_id], 's');
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User not found in database
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $user = $result->fetch_assoc();

    // Get recent lost items
    $lost_items_sql = "SELECT i.*, u.Name as Reporter_Name 
                       FROM items i 
                       LEFT JOIN user u ON i.User_ID = u.User_ID 
                       WHERE i.Item_Type = 'lost' 
                       ORDER BY i.Date_Reported DESC 
                       LIMIT 5";
    $lost_items = $conn->query($lost_items_sql);
    if (!$lost_items) {
        throw new Exception("Error fetching lost items: " . $conn->error);
    }

    // Get recent found items
    $found_items_sql = "SELECT i.*, u.Name as Reporter_Name 
                        FROM items i 
                        LEFT JOIN user u ON i.User_ID = u.User_ID 
                        WHERE i.Item_Type = 'found' 
                        ORDER BY i.Date_Reported DESC 
                        LIMIT 5";
    $found_items = $conn->query($found_items_sql);
    if (!$found_items) {
        throw new Exception("Error fetching found items: " . $conn->error);
    }

    // Get unread notifications count
    $notifications_sql = "SELECT COUNT(*) as count FROM notifications 
                         WHERE User_ID = ? AND Is_Read = 0";
    $stmt = executeQuery($conn, $notifications_sql, [$user_id], 's');
    $notifications = $stmt->get_result()->fetch_assoc()['count'];

    // Get user's rewards count
    $rewards_sql = "SELECT COUNT(*) as count FROM items 
                   WHERE User_ID = ? AND Item_Type = 'found' AND Status = 'claimed'";
    $stmt = executeQuery($conn, $rewards_sql, [$user_id], 's');
    $rewards = $stmt->get_result()->fetch_assoc()['count'];

} catch (Exception $e) {
    error_log("Home page error: " . $e->getMessage());
    $error_message = "An error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Lost & Found Dashboard</title>
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
        }

        body {
            font-family: 'Merriweather', serif;
            background: url('image2.jpg') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-color);
            line-height: 1.6;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: inherit;
            filter: blur(8px) brightness(0.5);
            z-index: 0;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.45); /* dark overlay */
            z-index: 0;
            pointer-events: none;
        }
        .top-bar, .container, .welcome-section, .dashboard-grid, .card {
            position: relative;
            z-index: 1;
        }
        .top-bar {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 1px;
        }
        .logo i {
            font-size: 1.8rem;
            color: var(--primary-dark);
        }
        .nav-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        .search-box {
            display: flex;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            padding: 0.5rem;
            border-radius: 50px;
            width: 300px;
        }
        .search-box input {
            background: transparent;
            border: none;
            color: white;
            padding: 0.5rem;
            width: 100%;
            outline: none;
            font-family: 'Merriweather', serif;
        }
        .search-box input::placeholder {
            color: rgba(255,255,255,0.7);
        }
        .search-box button {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
        }
        .nav-item {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Merriweather', serif;
        }
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .notification-badge {
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.8rem;
            margin-left: 0.3rem;
        }
        .user-menu {
            position: relative;
        }
        .user-menu-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 220px;
            box-shadow: var(--shadow);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        .user-menu:hover .user-menu-content {
            display: block;
        }
        .user-menu-item {
            color: var(--text-color);
            padding: 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: background-color 0.3s ease;
            font-family: 'Merriweather', serif;
        }
        .user-menu-item:hover {
            background-color: var(--secondary-color);
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .welcome-section {
            background: linear-gradient(135deg, rgba(0,150,136,0.95), rgba(0,121,107,0.95));
            color: #fff;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            font-family: 'Merriweather', serif;
        }
        .welcome-section h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        .card {
            background-color: rgba(255,255,255,0.98);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: transform 0.3s ease;
            font-family: 'Merriweather', serif;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .item-list {
            list-style: none;
        }
        .item-list li {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .item-list li:last-child {
            border-bottom: none;
        }
        .item-name {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.3rem;
        }
        .item-details {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 700;
            transition: background-color 0.3s ease;
            margin-top: 1rem;
            font-family: 'Merriweather', serif;
        }
        .btn:hover {
            background-color: var(--primary-dark);
        }
        .no-items {
            color: var(--text-light);
            font-style: italic;
            text-align: center;
            padding: 2rem 0;
        }
        .error-message {
            background-color: #ffebee;
            color: var(--danger-color);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
        }
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            .nav-menu {
                flex-direction: column;
                width: 100%;
            }
            .search-box {
                width: 100%;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-tree"></i>
            BRAC UNIVERSITY LOST & FOUND
        </div>
        <div class="nav-menu">
            <form action="search.php" method="GET" class="search-box">
                <input type="text" name="q" placeholder="Search items...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div class="nav-item report-dropdown" style="position: relative;">
                <span style="cursor:pointer;display:flex;align-items:center;gap:0.5rem;">
                    <i class="fas fa-plus-circle"></i> Report <i class="fas fa-caret-down"></i>
                </span>
                <div class="report-dropdown-content" style="display:none;position:absolute;top:110%;left:0;background:white;min-width:180px;box-shadow:0 2px 8px rgba(0,0,0,0.15);border-radius:8px;overflow:hidden;z-index:10;">
                    <a href="report.php?type=lost" class="user-menu-item" style="color:var(--primary-color);padding:1rem 1.5rem;display:block;text-decoration:none;">Lost Item</a>
                    <a href="report.php?type=found" class="user-menu-item" style="color:var(--primary-color);padding:1rem 1.5rem;display:block;text-decoration:none;">Found Item</a>
                </div>
            </div>
            <a href="notifications.php" class="nav-item">
                <i class="fas fa-bell"></i> Notifications
                <?php if (isset($notifications) && $notifications > 0): ?>
                    <span class="notification-badge"><?php echo $notifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="rewards.php" class="nav-item">
                <i class="fas fa-trophy"></i> Rewards
                <?php if (isset($rewards) && $rewards > 0): ?>
                    <span class="notification-badge"><?php echo $rewards; ?></span>
                <?php endif; ?>
            </a>
            <div class="user-menu">
                <a href="#" class="nav-item">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['Name'] ?? 'User'); ?>
                </a>
                <div class="user-menu-content">
                    <a href="profile.php" class="user-menu-item">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="my_reports.php" class="user-menu-item">
                        <i class="fas fa-list"></i> My Reports
                    </a>
                    <a href="settings.php" class="user-menu-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="logout.php" class="user-menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <div class="welcome-section">
                <h2>Welcome back, <?php echo htmlspecialchars($user['Name']); ?>!</h2>
                <p>Here's what's happening in the Lost & Found system.</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <h3><i class="fas fa-tree"></i> Recent Lost Items</h3>
                    <?php if ($lost_items && $lost_items->num_rows > 0): ?>
                        <ul class="item-list">
                            <?php while($item = $lost_items->fetch_assoc()): ?>
                                <li>
                                    <div class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                    <div class="item-details">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['Location']); ?><br>
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['Reporter_Name']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-items">No lost items reported recently.</p>
                    <?php endif; ?>
                    <a href="search.php?type=lost" class="btn">
                        <i class="fas fa-list"></i> View All Lost Items
                    </a>
                </div>

                <div class="card">
                    <h3><i class="fas fa-tree"></i> Recent Found Items</h3>
                    <?php if ($found_items && $found_items->num_rows > 0): ?>
                        <ul class="item-list">
                            <?php while($item = $found_items->fetch_assoc()): ?>
                                <li>
                                    <div class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                    <div class="item-details">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['Location']); ?><br>
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['Reporter_Name']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-items">No found items reported recently.</p>
                    <?php endif; ?>
                    <a href="search.php?type=found" class="btn">
                        <i class="fas fa-list"></i> View All Found Items
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Dropdown for Report menu
    document.addEventListener('DOMContentLoaded', function() {
        var reportDropdown = document.querySelector('.report-dropdown');
        var dropdownContent = document.querySelector('.report-dropdown-content');
        if(reportDropdown && dropdownContent) {
            reportDropdown.addEventListener('mouseenter', function() {
                dropdownContent.style.display = 'block';
            });
            reportDropdown.addEventListener('mouseleave', function() {
                dropdownContent.style.display = 'none';
            });
        }
    });
    </script>
</body>
</html>

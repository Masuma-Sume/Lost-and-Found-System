<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get unread notifications count
    $notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

    // Get user's lost items
    $lost_items_sql = "SELECT * FROM items WHERE User_ID = ? AND Item_Type = 'lost' ORDER BY Date_Reported DESC";
    $stmt = executeQuery($conn, $lost_items_sql, [$user_id], 's');
    $lost_items = $stmt->get_result();

    // Get user's found items
    $found_items_sql = "SELECT * FROM items WHERE User_ID = ? AND Item_Type = 'found' ORDER BY Date_Reported DESC";
    $stmt = executeQuery($conn, $found_items_sql, [$user_id], 's');
    $found_items = $stmt->get_result();

} catch (Exception $e) {
    error_log("My Reports error: " . $e->getMessage());
    $error_message = "An error occurred while fetching your reports. Please try again later.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - My Reports</title>
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
            background: rgba(0, 0, 0, 0.45);
            z-index: 0;
            pointer-events: none;
        }
        .top-bar, .container, .section, .item-card {
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
        .nav-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
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
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .section {
            background-color: rgba(255,255,255,0.98);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section h2 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--border-color);
        }
        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        .item-card {
            background-color: #f8f9fa;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border: 1px solid #eee;
            transition: transform 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-5px);
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .item-name {
            font-weight: bold;
            color: var(--primary-color);
            margin: 0;
        }
        .item-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background-color: #e6f2ff;
            color: var(--primary-color);
        }
        .item-status.claimed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .item-details {
            color: var(--text-light);
            font-size: 0.95em;
            margin: 10px 0;
        }
        .item-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: var(--primary-color);
            color: white;
            font-family: 'Merriweather', serif;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
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
            .container {
                padding: 0 5px;
            }
            .section {
                padding: 1rem;
            }
            .item-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">BRAC UNIVERSITY LOST & FOUND</div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="my_claims.php" class="nav-item">
                <i class="fas fa-hand-paper"></i> My Claims
            </a>
            <a href="notifications.php" class="nav-item">
                <i class="fas fa-bell"></i> Notifications
                <?php if (isset($notifications) && $notifications > 0): ?>
                    <span class="notification-badge"><?php echo $notifications; ?></span>
                <?php endif; ?>
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
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <div class="section">
                <h2>My Lost Items</h2>
                <?php if ($lost_items && $lost_items->num_rows > 0): ?>
                    <div class="item-grid">
                        <?php while($item = $lost_items->fetch_assoc()): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                                    <span class="item-status <?php echo $item['Status'] === 'claimed' ? 'claimed' : ''; ?>">
                                        <?php echo ucfirst($item['Status']); ?>
                                    </span>
                                </div>
                                <div class="item-details">
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($item['Location']); ?></p>
                                    <p><strong>Date Reported:</strong> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?></p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($item['Description']); ?></p>
                                </div>
                                <div class="item-actions">
                                    <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($item['Status'] === 'open'): ?>
                                        <a href="edit_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="no-items">You haven't reported any lost items yet.</p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>My Found Items</h2>
                <?php if ($found_items && $found_items->num_rows > 0): ?>
                    <div class="item-grid">
                        <?php while($item = $found_items->fetch_assoc()): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                                    <span class="item-status <?php echo $item['Status'] === 'claimed' ? 'claimed' : ''; ?>">
                                        <?php echo ucfirst($item['Status']); ?>
                                    </span>
                                </div>
                                <div class="item-details">
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($item['Location']); ?></p>
                                    <p><strong>Date Reported:</strong> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?></p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($item['Description']); ?></p>
                                </div>
                                <div class="item-actions">
                                    <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($item['Status'] === 'open'): ?>
                                        <a href="edit_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="no-items">You haven't reported any found items yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
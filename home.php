<?php
require_once 'config.php';
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
    $notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;
    // Ensure notifications is set to 0 if null
    $notifications = $notifications ?? 0;

    // Get user's rewards count
    $rewards_sql = "SELECT COUNT(*) as count FROM items 
                   WHERE User_ID = ? AND Item_Type = 'found' AND Status = 'claimed'";
    $stmt = executeQuery($conn, $rewards_sql, [$user_id], 's');
    $rewards = $stmt->get_result()->fetch_assoc()['count'];

    // 1. PHP: Fetch top hotspots
    $hotspots = $conn->query("SELECT Location, COUNT(*) as count FROM items GROUP BY Location ORDER BY count DESC LIMIT 10");

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
            min-height: 75px; /* Increased height to fit profile button better */
        }
        .logo {
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 1px;
            white-space: nowrap;
            min-width: 280px;
            flex-shrink: 0;
        }
        .logo i {
            font-size: 1.8rem;
            color: var(--primary-dark);
        }
        .nav-menu {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            gap: 2rem;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }
        
        .nav-center {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }
        .search-box {
            display: flex;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            width: 350px;
            flex-shrink: 0;
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
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Merriweather', serif;
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: visible; /* Ensure rounded corners aren't cut off */
        }
        .nav-item:hover {
            background-color: rgba(255,255,255,0.15);
            transform: translateY(-1px);
        }
        .notification-badge {
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            margin-left: 0.3rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            background: linear-gradient(135deg, rgba(0,150,136,0.95), rgba(0,121,107,0.95));
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            font-family: 'Merriweather', serif;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .action-icon {
            font-size: 2rem;
        }
        @media (max-width: 1200px) {
            .nav-menu {
                max-width: 95%;
                gap: 1rem;
            }
            .search-box {
                width: 280px;
            }
            .logo {
                min-width: 250px;
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
                min-height: auto; /* Allow height to adjust based on content */
            }
            .nav-menu {
                flex-direction: column;
                width: 100%;
                max-width: none;
                gap: 1rem;
            }
            .nav-left, .nav-center, .nav-right {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
                align-items: stretch; /* Make items fill width */
            }
            .nav-right {
                margin-top: 1rem;
                border-top: 1px solid rgba(255,255,255,0.2);
                padding-top: 1rem;
            }
            .search-box {
                width: 100%;
            }
            .logo {
                min-width: auto;
                font-size: 1.1rem;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            .nav-item {
                justify-content: center;
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
             <div class="nav-left">
                 <a href="notifications.php" class="nav-item">
                     <i class="fas fa-bell"></i> Notifications
                     <?php if (isset($notifications) && $notifications > 0): ?>
                         <span class="notification-badge"><?php echo $notifications; ?></span>
                     <?php endif; ?>
                 </a>
             </div>
             
             <div class="nav-center">
                 <form action="search.php" method="GET" class="search-box">
                     <input type="text" name="q" placeholder="Search items...">
                     <button type="submit"><i class="fas fa-search"></i></button>
                 </form>
             </div>
             
             <div class="nav-right">
                 <a href="profile.php" class="nav-item">
                     <i class="fas fa-user"></i> Profile
                 </a>
                 <a href="logout.php" class="nav-item">
                     <i class="fas fa-sign-out-alt"></i> Logout
                 </a>
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

            <div class="quick-actions">
                <a href="#" class="action-btn" id="hotspot-btn" title="View Hotspots">
                    <div class="action-icon">
                        <i class="fas fa-fire" style="animation:pulse 2s infinite;"></i>
                    </div>
                    <div>Hotspot</div>
                    <small style="background:#ff4757;color:white;padding:2px 8px;border-radius:10px;font-size:0.7rem;">HOT</small>
                </a>
                
                <a href="hotspots.php" class="action-btn" title="Detailed Hotspots Analytics">
                    <div class="action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>Analytics</div>
                </a>
                
                <a href="report_lost.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div>Report Lost</div>
                </a>
                
                <a href="report_found.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div>Report Found</div>
                </a>
                
                <a href="my_claims.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-hand-paper"></i>
                    </div>
                    <div>My Claims</div>
                </a>
                
                                 <a href="notifications.php" class="action-btn">
                     <div class="action-icon">
                         <i class="fas fa-bell"></i>
                     </div>
                     <div>View All</div>
                     <small>Notifications</small>
                 </a>
                
                <a href="rewards.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div>Rewards</div>
                    <?php if (isset($rewards) && $rewards > 0): ?>
                        <small><?php echo $rewards; ?> earned</small>
                    <?php endif; ?>
                </a>
                
                <a href="my_reports.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>My Reports</div>
                </a>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <h3><i class="fas fa-tree"></i> Recent Lost Items</h3>
                    <?php if ($lost_items && $lost_items->num_rows > 0): ?>
                        <ul class="item-list">
                            <?php while($item = $lost_items->fetch_assoc()): ?>
                                <li>
                                    <?php if (!empty($item['Image_URL'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['Image_URL']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px; float: left;">
                                    <?php endif; ?>
                                    <div class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                    <div class="item-details">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['Location']); ?><br>
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['Reporter_Name']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?>
                                    </div>
                                    <div style="clear: both;"></div>
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
                                    <?php if (!empty($item['Image_URL'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['Image_URL']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px; float: left;">
                                    <?php endif; ?>
                                    <div class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                    <div class="item-details">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['Location']); ?><br>
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['Reporter_Name']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?>
                                    </div>
                                    <div style="clear: both;"></div>
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

    <div id="hotspot-modal" class="modal" style="display:none;position:fixed;z-index:2000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;backdrop-filter:blur(5px);">
        <div class="modal-content" style="background:linear-gradient(135deg, #fff 0%, #f8f9fa 100%);border-radius:20px;max-width:600px;width:90%;margin:auto;padding:0;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;overflow:hidden;">
            <div style="background:linear-gradient(135deg, #009688 0%, #00796B 100%);padding:2rem;color:white;text-align:center;">
                <span id="close-hotspot" style="position:absolute;top:15px;right:20px;font-size:1.8rem;cursor:pointer;color:white;transition:all 0.3s ease;">&times;</span>
                <h2 style="margin:0;font-size:1.8rem;font-weight:700;">
                    <i class="fas fa-fire" style="margin-right:10px;animation:pulse 2s infinite;"></i> 
                    Location Hotspots
                </h2>
                <p style="margin:10px 0 0 0;opacity:0.9;font-size:1rem;">Most active areas on campus</p>
            </div>
            
            <div style="padding:2rem;max-height:400px;overflow-y:auto;">
                <?php if ($hotspots && $hotspots->num_rows > 0): $rank=1; while($row = $hotspots->fetch_assoc()): ?>
                    <div style="display:flex;align-items:center;padding:1rem;margin-bottom:1rem;background:white;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,0.1);transition:all 0.3s ease;border-left:4px solid #009688;">
                        <div style="background:linear-gradient(135deg, #009688 0%, #00796B 100%);color:white;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;margin-right:1rem;">
                            <?php echo $rank; ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;color:#00796B;font-size:1.1rem;margin-bottom:0.3rem;">
                                <?php echo htmlspecialchars($row['Location']); ?>
                            </div>
                            <div style="color:#666;font-size:0.9rem;">
                                <i class="fas fa-map-marker-alt"></i> Campus Location
                            </div>
                        </div>
                        <div style="background:linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);color:#009688;border-radius:20px;padding:0.5rem 1rem;font-weight:700;font-size:1rem;box-shadow:0 2px 8px rgba(0,150,136,0.2);">
                            <?php echo $row['count']; ?> items
                        </div>
                    </div>
                <?php $rank++; endwhile; else: ?>
                    <div style="text-align:center;padding:2rem;color:#666;">
                        <i class="fas fa-info-circle" style="font-size:3rem;color:#009688;margin-bottom:1rem;"></i>
                        <p>No hotspot data available yet.</p>
                        <p style="font-size:0.9rem;margin-top:0.5rem;">Start reporting items to see hotspots!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="background:#f8f9fa;padding:1.5rem;text-align:center;border-top:1px solid #e9ecef;">
                <a href="hotspots.php" style="background:linear-gradient(135deg, #009688 0%, #00796B 100%);color:white;padding:0.8rem 2rem;border-radius:25px;text-decoration:none;font-weight:700;display:inline-block;transition:all 0.3s ease;box-shadow:0 4px 15px rgba(0,150,136,0.3);">
                    <i class="fas fa-chart-line" style="margin-right:8px;"></i>
                    View Detailed Analytics
                </a>
            </div>
        </div>
    </div>

    <script>
    // Hotspot modal logic
    document.addEventListener('DOMContentLoaded', function() {
        var hotspotBtn = document.getElementById('hotspot-btn');
        var hotspotModal = document.getElementById('hotspot-modal');
        var closeHotspot = document.getElementById('close-hotspot');
        if(hotspotBtn && hotspotModal && closeHotspot) {
            hotspotBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hotspotModal.style.display = 'flex';
            });
            closeHotspot.addEventListener('click', function() {
                hotspotModal.style.display = 'none';
            });
            window.addEventListener('click', function(e) {
                if (e.target === hotspotModal) {
                    hotspotModal.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>
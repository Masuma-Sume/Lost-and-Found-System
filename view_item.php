<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

if (!$item_id) {
    header("Location: search.php");
    exit();
}

try {
    // Get item details with category and reporter information
    $sql = "SELECT i.*, u.Name as Reporter_Name, u.Contact_No as Reporter_Contact, c.Category_Name
            FROM items i 
            LEFT JOIN user u ON i.User_ID = u.User_ID 
            LEFT JOIN categories c ON i.Category_ID = c.Category_ID
            WHERE i.Item_ID = ?";
    
    $stmt = executeQuery($conn, $sql, [$item_id], 'i');
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: search.php");
        exit();
    }
    
    $item = $result->fetch_assoc();
    
    // Get claim count
    $claim_sql = "SELECT COUNT(*) as count FROM claims WHERE Item_ID = ?";
    $stmt = executeQuery($conn, $claim_sql, [$item_id], 'i');
    $claim_count = $stmt->get_result()->fetch_assoc()['count'];
    
} catch (Exception $e) {
    error_log("View item error: " . $e->getMessage());
    $error_message = "An error occurred while loading the item.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Item Details</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #009688;
            --primary-dark: #00796B;
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
            gap: 1.5rem;
        }
        
        .nav-item {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .notification-badge {
            background-color: #ff5722;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            margin-left: 0.25rem;
            font-weight: bold;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .item-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .item-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .item-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .item-type-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
        
        .item-content {
            padding: 2rem;
        }
        
        .item-image-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .item-image {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            object-fit: cover;
        }
        
        .item-image-placeholder {
            width: 300px;
            height: 300px;
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0 auto;
        }
        
        .item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-group {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .detail-group h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            color: var(--text-light);
        }
        
        .item-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
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
            
            .item-details {
                grid-template-columns: 1fr;
            }
            
            .item-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
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
            <a href="home.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="my_reports.php" class="nav-item">
                <i class="fas fa-list"></i> My Reports
            </a>
            <a href="my_claims.php" class="nav-item">
                <i class="fas fa-hand-paper"></i> My Claims
            </a>
            <a href="search.php" class="nav-item">
                <i class="fas fa-search"></i> Search
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
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <div class="item-card">
                <div class="item-header">
                    <h1 class="item-title"><?php echo htmlspecialchars($item['Item_Name']); ?></h1>
                    <span class="item-type-badge">
                        <i class="fas fa-<?php echo $item['Item_Type'] === 'lost' ? 'search' : 'hand-holding'; ?>"></i>
                        <?php echo ucfirst($item['Item_Type']); ?> Item
                    </span>
                </div>
                
                <div class="item-content">
                    <div class="item-image-section">
                        <?php if (!empty($item['Image_URL'])): ?>
                            <img src="<?php echo htmlspecialchars($item['Image_URL']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" 
                                 class="item-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="item-image-placeholder" style="display: none;">
                                <i class="fas fa-image"></i> Image not available
                            </div>
                        <?php else: ?>
                            <div class="item-image-placeholder">
                                <i class="fas fa-image"></i> No image available
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-details">
                        <div class="detail-group">
                            <h3><i class="fas fa-info-circle"></i> Item Information</h3>
                            <div class="detail-item">
                                <div class="detail-label">Category</div>
                                <div class="detail-value"><?php echo htmlspecialchars($item['Category_Name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span style="color: <?php echo $item['Status'] === 'open' ? '#28a745' : '#dc3545'; ?>; font-weight: 700;">
                                        <?php echo ucfirst($item['Status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($item['Description'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Description</div>
                                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($item['Description'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="detail-group">
                            <h3><i class="fas fa-map-marker-alt"></i> Location & Date</h3>
                            <div class="detail-item">
                                <div class="detail-label">Location</div>
                                <div class="detail-value"><?php echo htmlspecialchars($item['Location']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Date <?php echo $item['Item_Type'] === 'lost' ? 'Lost' : 'Found'; ?></div>
                                <div class="detail-value"><?php echo date('F d, Y', strtotime($item['Date_Lost_Found'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Reported On</div>
                                <div class="detail-value"><?php echo date('F d, Y \a\t h:i A', strtotime($item['Date_Reported'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <h3><i class="fas fa-user"></i> Reporter Information</h3>
                            <div class="detail-item">
                                <div class="detail-label">Reported By</div>
                                <div class="detail-value"><?php echo htmlspecialchars($item['Reporter_Name']); ?></div>
                            </div>
                            <?php if (!empty($item['Reporter_Contact'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Contact Number</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($item['Reporter_Contact']); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($claim_count > 0): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Claims</div>
                                    <div class="detail-value">
                                        <span style="background: #ff4444; color: white; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.9rem;">
                                            <?php echo $claim_count; ?> claim<?php echo $claim_count > 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="item-actions">
                        <a href="search.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Search
                        </a>
                        
                        <?php if ($item['Item_Type'] === 'found' && $item['User_ID'] !== $user_id): ?>
                            <a href="claim_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-primary">
                                <i class="fas fa-hand-holding"></i> Claim This Item
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($item['User_ID'] === $user_id): ?>
                            <a href="edit_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Item
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
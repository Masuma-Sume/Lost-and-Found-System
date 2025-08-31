<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$success_message = $error_message = '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Ensure Approval_Status column exists (idempotent)
try {
    $col = $conn->query("SHOW COLUMNS FROM items LIKE 'Approval_Status'");
    if (!$col || $col->num_rows === 0) {
        $conn->query("ALTER TABLE items ADD COLUMN Approval_Status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER Status");
    }
} catch (Exception $e) {
    error_log('Failed ensuring Approval_Status column: '.$e->getMessage());
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['item_id'])) {
        $item_id = intval($_POST['item_id']);
        $action = $_POST['action'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : null);
        if ($status === null) {
            $error_message = 'Invalid action.';
        } else {
            // Update item approval status
            executeQuery($conn, "UPDATE items SET Approval_Status = ? WHERE Item_ID = ?", [$status, $item_id], 'si');
            
            // Log admin action
            $log_sql = "INSERT INTO admin_logs (Admin_ID, Action, Description) VALUES (?, ?, ?)";
            $description = "Item #$item_id $status" . ($admin_notes ? " - Note: $admin_notes" : "");
            executeQuery($conn, $log_sql, [$_SESSION['user_id'], 'item_approval', $description], 'sss');
            
            // Notify the reporter
            $info = executeQuery($conn, "SELECT i.Item_Name, u.User_ID, u.Name FROM items i LEFT JOIN user u ON i.User_ID = u.User_ID WHERE i.Item_ID = ?", [$item_id], 'i')->get_result()->fetch_assoc();
            if ($info && !empty($info['User_ID'])) {
                $msg = "Your item \"{$info['Item_Name']}\" has been $status by admin.";
                if ($admin_notes) { $msg .= " Note: $admin_notes"; }
                sendNotificationToUser($conn, $info['User_ID'], $item_id, 'status_update', $msg);
            }
            $success_message = "Item has been $status successfully.";
        }
    }

    // Build query with filters
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($filter_status !== 'all') {
        $where_conditions[] = "COALESCE(i.Approval_Status,'pending') = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
    
    if ($filter_type !== 'all') {
        $where_conditions[] = "i.Item_Type = ?";
        $params[] = $filter_type;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $items_sql = "SELECT i.*, u.Name AS Reporter_Name, c.Category_Name,
                         (SELECT COUNT(*) FROM claims cl WHERE cl.Item_ID = i.Item_ID) as claim_count
                  FROM items i
                  LEFT JOIN user u ON i.User_ID = u.User_ID
                  LEFT JOIN categories c ON i.Category_ID = c.Category_ID
                  $where_clause
                  ORDER BY i.Date_Reported DESC";
    
    $stmt = executeQuery($conn, $items_sql, $params, $types);
    $items = $stmt->get_result();

} catch (Exception $e) {
    error_log('Admin review items error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading items.';
    $items = null;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Review Items | BRAC UNIVERSITY LOST & FOUND</title>
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
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --gradient-primary: linear-gradient(135deg, #009688 0%, #00796B 100%);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
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
            background: var(--gradient-primary);
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
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
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
        }
        
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .page-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-light);
            font-size: 1.1rem;
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.9rem;
        }
        
        .filter-select {
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }
        
        .filter-btn {
            padding: 0.8rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 150, 136, 0.3);
        }
        
        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .success {
            background: #e8f5e9;
            color: #256029;
            border: 1px solid #c8e6c9;
        }
        
        .error {
            background: #ffebee;
            color: #8a1c1c;
            border: 1px solid #ffcdd2;
        }
        
        .items-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .item-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .item-card:hover {
            transform: translateY(-3px);
        }
        
        .item-header {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .item-image-placeholder {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 0.9rem;
            border: 2px solid #e0e0e0;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .type-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .type-lost {
            background: #f8d7da;
            color: #721c24;
        }
        
        .type-found {
            background: #d4edda;
            color: #155724;
        }
        
        .item-description {
            color: var(--text-color);
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .item-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .action-form {
            flex: 1;
        }
        
        .action-textarea {
            width: 100%;
            min-height: 80px;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.9rem;
            resize: vertical;
            margin-bottom: 1rem;
        }
        
        .action-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.8rem;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-approve {
            background: var(--success-color);
            color: white;
        }
        
        .btn-reject {
            background: var(--danger-color);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .no-items {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .no-items-icon {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .no-items-text {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .no-items-subtext {
            color: var(--text-light);
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .item-header {
                flex-direction: column;
            }
            
            .item-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-clipboard-check"></i> Review Items
        </div>
        <div class="nav-menu">
            <a href="admin_home.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin_review_claims.php" class="nav-item">
                <i class="fas fa-hand-paper"></i> Review Claims
            </a>
            <a href="admin_profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-clipboard-check"></i> Review Items
            </h1>
            <p class="page-subtitle">Approve or reject newly posted items from users</p>
        </div>

        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Approval Status</label>
                    <select name="status" class="filter-select">
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Item Type</label>
                    <select name="type" class="filter-select">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="lost" <?php echo $filter_type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $filter_type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </form>
        </div>

        <?php if ($success_message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($items && $items->num_rows > 0): ?>
            <div class="items-grid">
                <?php while($item = $items->fetch_assoc()): ?>
                    <div class="item-card">
                        <div class="item-header">
                            <?php if (!empty($item['Image_URL'])): ?>
                                <img src="<?php echo htmlspecialchars($item['Image_URL']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" 
                                     class="item-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="item-image-placeholder" style="display: none;">
                                    <i class="fas fa-image"></i> No Image
                                </div>
                            <?php else: ?>
                                <div class="item-image-placeholder">
                                    <i class="fas fa-image"></i> No Image
                                </div>
                            <?php endif; ?>
                            
                            <div class="item-info">
                                <h3 class="item-title"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                                
                                <div class="item-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($item['Reporter_Name'] ?? 'Anonymous'); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M d, Y H:i', strtotime($item['Date_Reported'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($item['Location']); ?></span>
                                    </div>
                                    <?php if ($item['Category_Name']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <span><?php echo htmlspecialchars($item['Category_Name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['claim_count'] > 0): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-hand-holding"></i>
                                            <span><?php echo $item['claim_count']; ?> claim(s)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                    <span class="status-badge status-<?php echo $item['Approval_Status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($item['Approval_Status'] ?? 'pending'); ?>
                                    </span>
                                    <span class="type-badge type-<?php echo $item['Item_Type']; ?>">
                                        <?php echo ucfirst($item['Item_Type']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($item['Description'])): ?>
                                    <div class="item-description">
                                        <strong>Description:</strong> <?php echo nl2br(htmlspecialchars($item['Description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (($item['Approval_Status'] ?? 'pending') === 'pending'): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="item_id" value="<?php echo (int)$item['Item_ID']; ?>">
                                <textarea name="admin_notes" class="action-textarea" 
                                          placeholder="Optional note to the reporter (e.g., reason for rejection, additional information needed)"></textarea>
                                <div class="action-buttons">
                                    <button class="btn btn-approve" name="action" value="approve" type="submit">
                                        <i class="fas fa-check"></i> Approve Item
                                    </button>
                                    <button class="btn btn-reject" name="action" value="reject" type="submit">
                                        <i class="fas fa-times"></i> Reject Item
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px; color: var(--text-light);">
                                <i class="fas fa-info-circle"></i> 
                                This item has been <?php echo $item['Approval_Status']; ?> on 
                                <?php echo date('M d, Y H:i', strtotime($item['Date_Reported'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-items">
                <div class="no-items-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="no-items-text">No items found</div>
                <div class="no-items-subtext">
                    <?php if ($filter_status !== 'all' || $filter_type !== 'all'): ?>
                        Try adjusting your filters to see more items.
                    <?php else: ?>
                        There are currently no items to review.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>



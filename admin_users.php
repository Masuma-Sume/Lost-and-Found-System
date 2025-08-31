<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$success_message = $error_message = '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_role = isset($_GET['role']) ? $_GET['role'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Handle user actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        switch ($action) {
            case 'activate':
                executeQuery($conn, "UPDATE user SET Account_Status = 'active', Login_Attempts = 0 WHERE User_ID = ?", [$user_id], 's');
                $success_message = "User account activated successfully.";
                break;
                
            case 'suspend':
                executeQuery($conn, "UPDATE user SET Account_Status = 'suspended' WHERE User_ID = ?", [$user_id], 's');
                $success_message = "User account suspended successfully.";
                break;
                
            case 'delete':
                // Only allow deletion of non-admin users
                $user_role = executeQuery($conn, "SELECT Role FROM user WHERE User_ID = ?", [$user_id], 's')->get_result()->fetch_assoc()['Role'];
                if ($user_role === 'admin') {
                    $error_message = "Cannot delete admin accounts.";
                } else {
                    executeQuery($conn, "DELETE FROM user WHERE User_ID = ?", [$user_id], 's');
                    $success_message = "User account deleted successfully.";
                }
                break;
                
            case 'reset_password':
                $new_password = password_hash('password123', PASSWORD_DEFAULT);
                executeQuery($conn, "UPDATE user SET Password = ?, Login_Attempts = 0 WHERE User_ID = ?", [$new_password, $user_id], 'ss');
                $success_message = "User password reset successfully. New password: password123";
                break;
                
            default:
                $error_message = "Invalid action.";
        }
        
        // Log admin action
        if (empty($error_message)) {
            $log_sql = "INSERT INTO admin_logs (Admin_ID, Action, Description) VALUES (?, ?, ?)";
            $description = "User $user_id $action" . ($admin_notes ? " - Note: $admin_notes" : "");
            executeQuery($conn, $log_sql, [$_SESSION['user_id'], 'user_management', $description], 'sss');
        }
    }

    // Build query with filters
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($filter_status !== 'all') {
        $where_conditions[] = "u.Account_Status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
    
    if ($filter_role !== 'all') {
        $where_conditions[] = "u.Role = ?";
        $params[] = $filter_role;
        $types .= 's';
    }
    
    if (!empty($search_query)) {
        $where_conditions[] = "(u.Name LIKE ? OR u.Email LIKE ? OR u.User_ID LIKE ?)";
        $search_param = "%$search_query%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $users_sql = "SELECT u.*, 
                         COUNT(i.Item_ID) as total_items,
                         COUNT(CASE WHEN i.Item_Type = 'lost' THEN 1 END) as lost_items,
                         COUNT(CASE WHEN i.Item_Type = 'found' THEN 1 END) as found_items,
                         COUNT(c.Claim_ID) as total_claims,
                         COUNT(CASE WHEN c.Claim_Status = 'approved' THEN 1 END) as approved_claims
                  FROM user u
                  LEFT JOIN items i ON u.User_ID = i.User_ID
                  LEFT JOIN claims c ON u.User_ID = c.Claimant_ID
                  $where_clause
                  GROUP BY u.User_ID
                  ORDER BY u.Created_At DESC";
    
    $stmt = executeQuery($conn, $users_sql, $params, $types);
    $users = $stmt->get_result();

    // Get statistics
    $total_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user'")->fetch_assoc()['count'];
    $active_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user' AND Account_Status = 'active'")->fetch_assoc()['count'];
    $suspended_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user' AND Account_Status = 'suspended'")->fetch_assoc()['count'];
    $total_admins = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'admin'")->fetch_assoc()['count'];

} catch (Exception $e) {
    error_log('Admin users error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading users.';
    $users = null;
    $total_users = $active_users = $suspended_users = $total_admins = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - User Management | BRAC UNIVERSITY LOST & FOUND</title>
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
            max-width: 1400px;
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
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
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
            grid-template-columns: 1fr 1fr 1fr auto;
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
        
        .filter-input, .filter-select {
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus, .filter-select:focus {
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
        
        .users-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-header {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 700;
        }
        
        .table-content {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .user-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr auto;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        
        .user-row:hover {
            background: #f8f9fa;
        }
        
        .user-row:last-child {
            border-bottom: none;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .user-name {
            font-weight: 700;
            color: var(--text-color);
        }
        
        .user-email {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .user-id {
            font-size: 0.8rem;
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }
        
        .role-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }
        
        .role-user {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .role-admin {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .stats-mini {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.9rem;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
        }
        
        .stat-label-mini {
            color: var(--text-light);
        }
        
        .stat-value-mini {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .user-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.5rem 0.8rem;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-activate {
            background: var(--success-color);
            color: white;
        }
        
        .btn-suspend {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-reset {
            background: var(--info-color);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .no-users {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }
        
        .no-users-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .user-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .user-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-user-cog"></i> User Management
        </div>
        <div class="nav-menu">
            <a href="admin_home.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin_review_items.php" class="nav-item">
                <i class="fas fa-clipboard-check"></i> Review Items
            </a>
            <a href="admin_review_claims.php" class="nav-item">
                <i class="fas fa-hand-paper"></i> Review Claims
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-cog"></i> User Management
            </h1>
            <p class="page-subtitle">Manage user accounts, view statistics, and control access</p>
        </div>

        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo number_format($active_users); ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-value"><?php echo number_format($suspended_users); ?></div>
                <div class="stat-label">Suspended Users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_admins); ?></div>
                <div class="stat-label">Admin Users</div>
            </div>
        </div>

        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Search Users</label>
                    <input type="text" name="search" class="filter-input" 
                           placeholder="Search by name, email, or ID"
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Account Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">User Role</label>
                    <select name="role" class="filter-select">
                        <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>Users</option>
                        <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-search"></i> Search
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

        <div class="users-table">
            <div class="table-header">
                <div class="user-row">
                    <div>User Information</div>
                    <div>Status</div>
                    <div>Role</div>
                    <div>Items Posted</div>
                    <div>Claims Made</div>
                    <div>Joined Date</div>
                    <div>Actions</div>
                </div>
            </div>
            
            <div class="table-content">
                <?php if ($users && $users->num_rows > 0): ?>
                    <?php while($user = $users->fetch_assoc()): ?>
                        <div class="user-row">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['Name']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['Email']); ?></div>
                                <div class="user-id"><?php echo htmlspecialchars($user['User_ID']); ?></div>
                            </div>
                            
                            <div>
                                <span class="status-badge status-<?php echo $user['Account_Status']; ?>">
                                    <?php echo ucfirst($user['Account_Status']); ?>
                                </span>
                            </div>
                            
                            <div>
                                <span class="role-badge role-<?php echo $user['Role']; ?>">
                                    <?php echo ucfirst($user['Role']); ?>
                                </span>
                            </div>
                            
                            <div class="stats-mini">
                                <div class="stat-item">
                                    <span class="stat-label-mini">Total:</span>
                                    <span class="stat-value-mini"><?php echo $user['total_items']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label-mini">Lost:</span>
                                    <span class="stat-value-mini"><?php echo $user['lost_items']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label-mini">Found:</span>
                                    <span class="stat-value-mini"><?php echo $user['found_items']; ?></span>
                                </div>
                            </div>
                            
                            <div class="stats-mini">
                                <div class="stat-item">
                                    <span class="stat-label-mini">Total:</span>
                                    <span class="stat-value-mini"><?php echo $user['total_claims']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label-mini">Approved:</span>
                                    <span class="stat-value-mini"><?php echo $user['approved_claims']; ?></span>
                                </div>
                            </div>
                            
                            <div style="font-size: 0.9rem; color: var(--text-light);">
                                <?php echo date('M d, Y', strtotime($user['Created_At'])); ?>
                            </div>
                            
                            <div class="user-actions">
                                <?php if ($user['Account_Status'] === 'suspended'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['User_ID']); ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="action-btn btn-activate" 
                                                onclick="return confirm('Activate this user account?')">
                                            <i class="fas fa-user-check"></i> Activate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['User_ID']); ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="action-btn btn-suspend" 
                                                onclick="return confirm('Suspend this user account?')">
                                            <i class="fas fa-user-slash"></i> Suspend
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['User_ID']); ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <button type="submit" class="action-btn btn-reset" 
                                            onclick="return confirm('Reset password for this user? New password will be: password123')">
                                        <i class="fas fa-key"></i> Reset PW
                                    </button>
                                </form>
                                
                                <?php if ($user['Role'] !== 'admin'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['User_ID']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn btn-delete" 
                                                onclick="return confirm('Are you sure you want to delete this user account? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-users">
                        <div class="no-users-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>No users found matching your criteria.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

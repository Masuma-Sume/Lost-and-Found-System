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

try {
    // Check database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : "Connection not established"));
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['claim_id'])) {
        $claim_id = intval($_POST['claim_id']);
        $action = $_POST['action'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : ($action === 'verify' ? 'verified' : null));
        if ($status === null) {
            $error_message = 'Invalid action.';
        } else {
            try {
                // Update claim status
                executeQuery($conn, "UPDATE claims SET Claim_Status = ?, Admin_Notes = ?, Updated_At = NOW() WHERE Claim_ID = ?", [$status, $admin_notes, $claim_id], 'ssi');
                
                // If approved, update item status to claimed
                if ($status === 'approved') {
                    $item_result = executeQuery($conn, "SELECT Item_ID FROM claims WHERE Claim_ID = ?", [$claim_id], 'i')->get_result();
                    if ($item_result && $item_result->num_rows > 0) {
                        $item_id = $item_result->fetch_assoc()['Item_ID'];
                        executeQuery($conn, "UPDATE items SET Status = 'claimed' WHERE Item_ID = ?", [$item_id], 'i');
                    }
                }
                
                // Log admin action
                $log_sql = "INSERT INTO admin_logs (Admin_ID, Action, Description) VALUES (?, ?, ?)";
                $description = "Claim #$claim_id $status" . ($admin_notes ? " - Note: $admin_notes" : "");
                executeQuery($conn, $log_sql, [$_SESSION['user_id'], 'claim_approval', $description], 'sss');
                
                // Notify the claimant
                $info_result = executeQuery($conn, "SELECT c.Claim_ID, i.Item_Name, u.User_ID, u.Name FROM claims c 
                                            LEFT JOIN items i ON c.Item_ID = i.Item_ID 
                                            LEFT JOIN user u ON c.Claimant_ID = u.User_ID 
                                            WHERE c.Claim_ID = ?", [$claim_id], 'i')->get_result();
                
                if ($info_result && $info_result->num_rows > 0) {
                    $info = $info_result->fetch_assoc();
                    if (!empty($info['User_ID'])) {
                        $msg = "Your claim for \"{$info['Item_Name']}\" has been $status by admin.";
                        if ($admin_notes) { $msg .= " Note: $admin_notes"; }
                        $item_id = isset($info['Item_ID']) ? $info['Item_ID'] : null;
                        sendNotificationToUser($conn, $info['User_ID'], $item_id, 'status_update', $msg);
                    }
                }
                
                $success_message = "Claim has been $status successfully.";
            } catch (Exception $e) {
                error_log("Admin claim action error: " . $e->getMessage());
                $error_message = "Error processing claim action. Please try again.";
            }
        }
    }

    // Build query with filters
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($filter_status !== 'all') {
        $where_conditions[] = "c.Claim_Status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
    
    if ($filter_type !== 'all') {
        $where_conditions[] = "i.Item_Type = ?";
        $params[] = $filter_type;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $claims_sql = "SELECT c.*, i.Item_Name, i.Item_Type, i.Location, i.Image_URL, i.Status as Item_Status,
                          u1.Name AS Claimant_Name, u1.Email AS Claimant_Email, u1.Contact_No AS Claimant_Phone,
                          u2.Name AS Reporter_Name, c2.Category_Name
                   FROM claims c
                   LEFT JOIN items i ON c.Item_ID = i.Item_ID
                   LEFT JOIN user u1 ON c.Claimant_ID = u1.User_ID
                   LEFT JOIN user u2 ON i.User_ID = u2.User_ID
                   LEFT JOIN categories c2 ON i.Category_ID = c2.Category_ID
                   $where_clause
                   ORDER BY c.Created_At DESC";
    
    $stmt = executeQuery($conn, $claims_sql, $params, $types);
    $claims = $stmt->get_result();

} catch (Exception $e) {
    error_log('Admin review claims error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading claims.';
    $claims = null;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Review Claims | BRAC UNIVERSITY LOST & FOUND</title>
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
        
        .claims-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .claim-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .claim-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .claim-card:hover {
            transform: translateY(-3px);
        }
        
        .claim-header {
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
        
        .claim-info {
            flex: 1;
        }
        
        .claim-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .claim-meta {
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
        
        .status-verified {
            background: #d1ecf1;
            color: #0c5460;
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
        
        .claim-description {
            color: var(--text-color);
            margin-bottom: 1rem;
            line-height: 1.6;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
        }
        
        .claimant-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .claimant-title {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .claimant-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .claim-actions {
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
            flex-wrap: wrap;
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
        
        .btn-verify {
            background: var(--info-color);
            color: white;
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
        
        .no-claims {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .no-claims-icon {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .no-claims-text {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .no-claims-subtext {
            color: var(--text-light);
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .claim-header {
                flex-direction: column;
            }
            
            .claim-actions {
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
            <i class="fas fa-hand-paper"></i> Review Claims
        </div>
        <div class="nav-menu">
            <a href="admin_home.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin_review_items.php" class="nav-item">
                <i class="fas fa-clipboard-check"></i> Review Items
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
                <i class="fas fa-hand-paper"></i> Review Claims
            </h1>
            <p class="page-subtitle">Review and approve item claims from users</p>
        </div>

        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Claim Status</label>
                    <select name="status" class="filter-select">
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo $filter_status === 'verified' ? 'selected' : ''; ?>>Verified</option>
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

        <?php if ($claims && $claims->num_rows > 0): ?>
            <div class="claims-grid">
                <?php while($claim = $claims->fetch_assoc()): ?>
                    <div class="claim-card">
                        <div class="claim-header">
                            <?php if (!empty($claim['Image_URL'])): ?>
                                <img src="<?php echo htmlspecialchars($claim['Image_URL']); ?>" 
                                     alt="<?php echo htmlspecialchars($claim['Item_Name']); ?>" 
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
                            
                            <div class="claim-info">
                                <h3 class="claim-title"><?php echo htmlspecialchars($claim['Item_Name']); ?></h3>
                                
                                <div class="claim-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>Claimed by: <?php echo htmlspecialchars($claim['Claimant_Name']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Claimed: <?php echo date('M d, Y H:i', strtotime($claim['Created_At'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($claim['Location']); ?></span>
                                    </div>
                                    <?php if ($claim['Category_Name']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <span><?php echo htmlspecialchars($claim['Category_Name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                    <span class="status-badge status-<?php echo $claim['Claim_Status']; ?>">
                                        <?php echo ucfirst($claim['Claim_Status']); ?>
                                    </span>
                                    <span class="type-badge type-<?php echo $claim['Item_Type']; ?>">
                                        <?php echo ucfirst($claim['Item_Type']); ?>
                                    </span>
                                </div>
                                
                                <div class="claimant-info">
                                    <div class="claimant-title">
                                        <i class="fas fa-user-circle"></i> Claimant Information
                                    </div>
                                    <div class="claimant-details">
                                        <div><strong>Name:</strong> <?php echo htmlspecialchars($claim['Claimant_Name']); ?></div>
                                        <div><strong>Email:</strong> <?php echo htmlspecialchars($claim['Claimant_Email']); ?></div>
                                        <?php if ($claim['Claimant_Phone']): ?>
                                            <div><strong>Phone:</strong> <?php echo htmlspecialchars($claim['Claimant_Phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($claim['Claim_Description'])): ?>
                                    <div class="claim-description">
                                        <strong>Claim Description:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($claim['Claim_Description'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php
                                    // Process verification answers if they exist or are NULL
                                    $similarity_percentage = 0;
                                    try {
                                        // Check if Verification_Answers exists and is not NULL
                                        if (isset($claim['Verification_Answers'])) {
                                            // Ensure Verification_Answers is valid JSON
                                            $verification_answers = json_decode($claim['Verification_Answers'], true);
                                            if ($verification_answers === null && json_last_error() !== JSON_ERROR_NONE) {
                                                // Invalid JSON, set to empty object
                                                $verification_answers = [];
                                                // Fix in database
                                                executeQuery($conn, "UPDATE claims SET Verification_Answers = '{}' WHERE Claim_ID = ?", [$claim['Claim_ID']], 'i');
                                                error_log("Fixed invalid JSON in Verification_Answers for claim ID: {$claim['Claim_ID']}");
                                            }
                                        } else {
                                            // Verification_Answers is NULL or doesn't exist, set to empty object
                                            $verification_answers = [];
                                            // Fix in database
                                            executeQuery($conn, "UPDATE claims SET Verification_Answers = '{}' WHERE Claim_ID = ?", [$claim['Claim_ID']], 'i');
                                            error_log("Added empty Verification_Answers for claim ID: {$claim['Claim_ID']}");
                                        }
                                        
                                        // Since the items table doesn't have these columns, we'll use empty values
                                        $item_details = [
                                            'color' => '',
                                            'brand' => '',
                                            'distinguishing_features' => '',
                                            'approximate_value' => '',
                                            'date_lost' => ''
                                        ];
                                        
                                        // Only calculate similarity if we have verification answers
                                        if (!empty($verification_answers)) {
                                            $similarity_percentage = calculateSimilarityPercentage($verification_answers, $item_details);
                                            
                                            // Update the verification score in database
                                            if ($claim['Verification_Score'] != $similarity_percentage) {
                                                updateClaimVerificationScore($conn, $claim['Claim_ID'], $similarity_percentage);
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log('Verification calculation error for claim ID ' . $claim['Claim_ID'] . ': ' . $e->getMessage());
                                        $similarity_percentage = 0;
                                    }
                                    
                                    // Only show verification section if we have verification answers
                                    if (isset($verification_answers) && !empty($verification_answers)):
                                    
                                    ?>
                                    <div class="verification-section" style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin: 1rem 0; border-left: 4px solid #17a2b8;">
                                        <h4 style="margin: 0 0 1rem 0; color: #17a2b8;">
                                            <i class="fas fa-percentage"></i> Verification Analysis
                                        </h4>
                                        
                                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                            <div style="flex: 1;">
                                                <div style="background: #e9ecef; border-radius: 10px; height: 20px; overflow: hidden;">
                                                    <div style="background: <?php echo $similarity_percentage >= 70 ? '#28a745' : ($similarity_percentage >= 50 ? '#ffc107' : '#dc3545'); ?>; 
                                                                 height: 100%; width: <?php echo $similarity_percentage; ?>%; 
                                                                 transition: width 0.3s ease;"></div>
                                                </div>
                                            </div>
                                            <div style="font-weight: bold; font-size: 1.1rem; color: <?php echo $similarity_percentage >= 70 ? '#28a745' : ($similarity_percentage >= 50 ? '#856404' : '#dc3545'); ?>;">
                                                <?php echo $similarity_percentage; ?>%
                                            </div>
                                        </div>
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                                            <div>
                                                <strong>Claimant's Answers:</strong>
                                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                                    <li><strong>Color:</strong> <?php echo htmlspecialchars($verification_answers['color'] ?? 'Not provided'); ?></li>
                                                    <li><strong>Brand:</strong> <?php echo htmlspecialchars($verification_answers['brand'] ?? 'Not provided'); ?></li>
                                                    <li><strong>Features:</strong> <?php echo htmlspecialchars($verification_answers['distinguishing_features'] ?? 'Not provided'); ?></li>
                                                    <li><strong>Value:</strong> <?php echo htmlspecialchars($verification_answers['approximate_value'] ?? 'Not provided'); ?></li>
                                                    <li><strong>Date Lost:</strong> <?php echo htmlspecialchars($verification_answers['date_lost'] ?? 'Not provided'); ?></li>
                                                </ul>
                                            </div>
                                            <div>
                                                <strong>Item Details:</strong>
                                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                                    <li><strong>Color:</strong> Not available in database</li>
                                                    <li><strong>Brand:</strong> Not available in database</li>
                                                    <li><strong>Features:</strong> Not available in database</li>
                                                    <li><strong>Value:</strong> Not available in database</li>
                                                    <li><strong>Date Lost:</strong> Not available in database</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-top: 1rem; padding: 0.5rem; background: <?php echo $similarity_percentage >= 70 ? '#d4edda' : ($similarity_percentage >= 50 ? '#fff3cd' : '#f8d7da'); ?>; 
                                                    border-radius: 5px; color: <?php echo $similarity_percentage >= 70 ? '#155724' : ($similarity_percentage >= 50 ? '#856404' : '#721c24'); ?>;">
                                            <strong>Recommendation:</strong> 
                                            <?php if ($similarity_percentage >= 70): ?>
                                                High similarity - Consider approving this claim
                                            <?php elseif ($similarity_percentage >= 50): ?>
                                                Moderate similarity - Review carefully before decision
                                            <?php else: ?>
                                                Low similarity - Additional verification may be needed
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($claim['Admin_Notes'])): ?>
                                    <div class="claim-description" style="background: #fff3cd; border-left-color: #ffc107;">
                                        <strong>Admin Notes:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($claim['Admin_Notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($claim['Claim_Status'] === 'pending'): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="claim_id" value="<?php echo (int)$claim['Claim_ID']; ?>">
                                <textarea name="admin_notes" class="action-textarea" 
                                          placeholder="Optional note to the claimant (e.g., verification details, reason for decision)"></textarea>
                                <div class="action-buttons">
                                    <button class="btn btn-verify" name="action" value="verify" type="submit">
                                        <i class="fas fa-check-double"></i> Verify Claim
                                    </button>
                                    <button class="btn btn-approve" name="action" value="approve" type="submit">
                                        <i class="fas fa-check"></i> Approve Claim
                                    </button>
                                    <button class="btn btn-reject" name="action" value="reject" type="submit">
                                        <i class="fas fa-times"></i> Reject Claim
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px; color: var(--text-light);">
                                <i class="fas fa-info-circle"></i> 
                                This claim has been <?php echo $claim['Claim_Status']; ?> on 
                                <?php echo date('M d, Y H:i', strtotime($claim['Updated_At'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-claims">
                <div class="no-claims-icon">
                    <i class="fas fa-hand-paper"></i>
                </div>
                <div class="no-claims-text">No claims found</div>
                <div class="no-claims-subtext">
                    <?php if ($filter_status !== 'all' || $filter_type !== 'all'): ?>
                        Try adjusting your filters to see more claims.
                    <?php else: ?>
                        There are currently no claims to review.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>



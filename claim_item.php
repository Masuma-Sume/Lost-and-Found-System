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
$success_message = $error_message = '';

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

if (!$item_id) {
    header("Location: search.php");
    exit();
}

try {
    // Get item details
    $sql = "SELECT i.*, u.Name as Reporter_Name, u.User_ID as Reporter_ID, c.Category_Name
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
    
    // Check if this is a found item and not reported by the current user
    if ($item['Item_Type'] !== 'found' || $item['User_ID'] === $user_id) {
        header("Location: view_item.php?id=" . $item_id);
        exit();
    }
    
    // Check if user has already claimed this item
    $check_sql = "SELECT * FROM claims WHERE Item_ID = ? AND Claimant_ID = ?";
    $check_stmt = executeQuery($conn, $check_sql, [$item_id, $user_id], 'is');
    $existing_claim = $check_stmt->get_result();
    
    if ($existing_claim->num_rows > 0) {
        $error_message = "You have already submitted a claim for this item.";
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
        $claim_description = trim($_POST['claim_description']);
        $item_color = trim($_POST['item_color'] ?? '');
        $item_brand = trim($_POST['item_brand'] ?? '');
        $item_distinguishing_features = trim($_POST['item_distinguishing_features'] ?? '');
        $item_approximate_value = trim($_POST['item_approximate_value'] ?? '');
        $item_date_lost = trim($_POST['item_date_lost'] ?? '');
        
        if (empty($claim_description)) {
            $error_message = "Please provide a description of why you believe this item belongs to you.";
        } elseif (empty($item_color) || empty($item_distinguishing_features)) {
            $error_message = "Please answer all required verification questions.";
        } else {
            // Prepare verification answers
            $verification_answers = json_encode([
                'color' => $item_color,
                'brand' => $item_brand,
                'distinguishing_features' => $item_distinguishing_features,
                'approximate_value' => $item_approximate_value,
                'date_lost' => $item_date_lost
            ]);
            
            // Insert claim with verification answers
            $claim_sql = "INSERT INTO claims (Item_ID, Claimant_ID, Claim_Description, Verification_Answers, Claim_Status) VALUES (?, ?, ?, ?, 'pending')";
            $claim_stmt = executeQuery($conn, $claim_sql, [$item_id, $user_id, $claim_description, $verification_answers], 'isss');
            
            if ($claim_stmt) {
                // Send notification to the item reporter
                $reporter_id = $item['Reporter_ID'];
                $notification_message = "Someone has claimed your found item: \"" . $item['Item_Name'] . "\". Review the claim in your dashboard.";
                sendNotificationToUser($conn, $reporter_id, $item_id, 'claim', $notification_message);
                
                $success_message = "Your claim has been submitted successfully. The item reporter will be notified.";
            } else {
                $error_message = "Error submitting your claim. Please try again.";
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Claim item error: " . $e->getMessage());
    $error_message = "An error occurred while processing your claim.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Claim Item</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: 'Merriweather', serif; background: #f5f5f5; margin: 0; padding: 0; }
        .top-bar { background: #009688; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav-menu { display: flex; gap: 1.5rem; align-items: center; }
        .nav-item { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 50px; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .nav-item:hover { background-color: rgba(255,255,255,0.1); }
        .notification-badge { background-color: #ff5722; color: white; border-radius: 50%; padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; font-weight: bold; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #009688; text-align: center; margin-bottom: 30px; }
        .item-details { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .item-details h2 { color: #333; margin-top: 0; font-size: 1.4rem; }
        .item-property { margin-bottom: 10px; display: flex; }
        .property-label { font-weight: bold; width: 150px; color: #555; }
        .property-value { flex: 1; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; }
        textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; min-height: 150px; }
        .btn { display: inline-block; padding: 12px 24px; background: #009688; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #00796b; }
        .btn-secondary { background: #757575; }
        .btn-secondary:hover { background: #616161; }
        .buttons { display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
        .success { background: #e0f7fa; color: #00796B; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Lost & Found</div>
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
            <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Claim Item</h1>
        
        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
            <div class="buttons">
                <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn">View Item Details</a>
                <a href="search.php" class="btn btn-secondary">Back to Search</a>
            </div>
        <?php else: ?>
            <?php if ($error_message): ?>
                <div class="error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="item-details">
                <h2>Item Information</h2>
                <div class="item-property">
                    <div class="property-label">Item Name:</div>
                    <div class="property-value"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                </div>
                <div class="item-property">
                    <div class="property-label">Category:</div>
                    <div class="property-value"><?php echo htmlspecialchars($item['Category_Name']); ?></div>
                </div>
                <div class="item-property">
                    <div class="property-label">Location Found:</div>
                    <div class="property-value"><?php echo htmlspecialchars($item['Location']); ?></div>
                </div>
                <div class="item-property">
                    <div class="property-label">Date Found:</div>
                    <div class="property-value"><?php echo date('F d, Y', strtotime($item['Date_Lost_Found'])); ?></div>
                </div>
                <?php if (!empty($item['Description'])): ?>
                <div class="item-property">
                    <div class="property-label">Description:</div>
                    <div class="property-value"><?php echo htmlspecialchars($item['Description']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($error_message) || $error_message !== "You have already submitted a claim for this item."): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="claim_description">Why do you believe this item belongs to you?</label>
                    <textarea id="claim_description" name="claim_description" placeholder="Please provide specific details about the item that only the owner would know. This will help verify your claim."></textarea>
                </div>
                
                <h3 style="margin-top: 30px; color: #009688;">Verification Questions</h3>
                <p style="margin-bottom: 20px;">Please answer the following questions to verify your ownership. The more accurate your answers, the more likely your claim will be approved.</p>
                
                <div class="form-group">
                    <label for="item_color">What is the color of the item? <span style="color: red;">*</span></label>
                    <input type="text" id="item_color" name="item_color" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="item_brand">What is the brand of the item? (if applicable)</label>
                    <input type="text" id="item_brand" name="item_brand" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="item_distinguishing_features">Describe any distinguishing features of the item (scratches, engravings, stickers, etc.) <span style="color: red;">*</span></label>
                    <textarea id="item_distinguishing_features" name="item_distinguishing_features" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 80px;"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="item_approximate_value">What is the approximate value of the item?</label>
                    <input type="text" id="item_approximate_value" name="item_approximate_value" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="item_date_lost">When did you lose this item? (approximate date)</label>
                    <input type="date" id="item_date_lost" name="item_date_lost" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="buttons">
                    <button type="submit" class="btn">Submit Claim</button>
                    <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            <?php else: ?>
            <div class="buttons">
                <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn">View Item Details</a>
                <a href="search.php" class="btn btn-secondary">Back to Search</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
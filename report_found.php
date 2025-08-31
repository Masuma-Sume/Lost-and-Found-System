<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = '';

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category_id = intval($_POST['category_id']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    $date_found = $_POST['date_found'];
    $color = trim($_POST['color'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $distinguishing_features = trim($_POST['distinguishing_features'] ?? '');
    $approximate_value = trim($_POST['approximate_value'] ?? '');

    
    // Handle image upload
    $image_url = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_url = $upload_path;
            } else {
                $error_message = "Failed to upload image. Please try again.";
            }
        } else {
            $error_message = "Invalid file type. Please upload JPG, JPEG, PNG, or GIF files only.";
        }
    }

    if ($item_name && $category_id && $location && $date_found) {
        // Insert found item
        $sql = "INSERT INTO items (User_ID, Item_Name, Item_Type, Category_ID, Location, Description, Color, Brand, Distinguishing_Features, Approximate_Value, Date_Lost_Found, Status, Image_URL) VALUES (?, ?, 'found', ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssissssssss', $user_id, $item_name, $category_id, $location, $description, $color, $brand, $distinguishing_features, $approximate_value, $date_found, $image_url);
        if ($stmt->execute()) {
            $item_id = $stmt->insert_id;
            // Assign reward
            $reward_points = 500;
            $reward_sql = "INSERT INTO rewards (User_ID, Item_ID, Points, Status) VALUES (?, ?, ?, 'awarded')";
            $stmt2 = $conn->prepare($reward_sql);
            if (!$stmt2) { die('Reward prepare failed: ' . $conn->error); }
            $stmt2->bind_param('sii', $user_id, $item_id, $reward_points);
            if (!$stmt2->execute()) { die('Reward execute failed: ' . $stmt2->error); }
            // Update user_rewards
            $update_points_sql = "INSERT INTO user_rewards (User_ID, Total_Points) VALUES (?, ?) ON DUPLICATE KEY UPDATE Total_Points = Total_Points + ?";
            $stmt3 = $conn->prepare($update_points_sql);
            if (!$stmt3) { die('User rewards prepare failed: ' . $conn->error); }
            $stmt3->bind_param('sii', $user_id, $reward_points, $reward_points);
            if (!$stmt3->execute()) { die('User rewards execute failed: ' . $stmt3->error); }
            // Badge assignment (example: 5 found items = Super Helper)
            $badge_check_sql = "SELECT COUNT(*) as found_count FROM items WHERE User_ID = ? AND Item_Type = 'found'";
            $stmt4 = $conn->prepare($badge_check_sql);
            if (!$stmt4) { die('Badge check prepare failed: ' . $conn->error); }
            $stmt4->bind_param('s', $user_id);
            if (!$stmt4->execute()) { die('Badge check execute failed: ' . $stmt4->error); }
            $result = $stmt4->get_result();
            $row = $result->fetch_assoc();
            if ($row['found_count'] == 5) {
                $badge_id_sql = "SELECT Badge_ID FROM badges WHERE Badge_Name = 'Super Helper'";
                $badge_result = $conn->query($badge_id_sql);
                if ($badge = $badge_result->fetch_assoc()) {
                    $badge_id = $badge['Badge_ID'];
                    $assign_badge_sql = "INSERT INTO rewards (User_ID, Item_ID, Points, Badge_ID, Status) VALUES (?, ?, 0, ?, 'awarded')";
                    $stmt5 = $conn->prepare($assign_badge_sql);
                    if (!$stmt5) { die('Badge assign prepare failed: ' . $conn->error); }
                    $stmt5->bind_param('sii', $user_id, $item_id, $badge_id);
                    if (!$stmt5->execute()) { die('Badge assign execute failed: ' . $stmt5->error); }
                }
            }
            
            // Send notifications to all users
            $notification_sent = sendNotificationToAllUsers($conn, $user_id, $item_id, $item_name, 'found', $location);
            
            if ($notification_sent) {
                $success_message = "Found item reported successfully! You have earned a reward. All users have been notified.";
            } else {
                $success_message = "Found item reported successfully! You have earned a reward. (Notification system temporarily unavailable)";
            }
        } else {
            $error_message = "Error reporting found item. Please try again.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Fetch categories for the dropdown
$categories = $conn->query("SELECT Category_ID, Category_Name FROM categories ORDER BY Category_Name ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Report Found Item</title>
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
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #009688; text-align: center; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; color: #333; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; }
        button { background: #009688; color: #fff; border: none; padding: 12px 25px; border-radius: 5px; font-size: 1em; cursor: pointer; width: 100%; }
        button:hover { background: #00796B; }
        .success { background: #e0f7fa; color: #00796B; padding: 12px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
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
        <h1>Report Found Item</h1>
        <?php if ($success_message): ?><div class="success"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="error"><?php echo $error_message; ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="item_name">Item Name *</label>
                <input type="text" id="item_name" name="item_name" required>
            </div>
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['Category_ID']; ?>"><?php echo htmlspecialchars($cat['Category_Name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" id="location" name="location" required>
            </div>
            <div class="form-group">
                <label for="date_found">Date Found *</label>
                <input type="date" id="date_found" name="date_found" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            
            <h3 style="margin-top: 30px; color: #009688; border-bottom: 2px solid #009688; padding-bottom: 10px;">Item Details</h3>
            <p style="margin-bottom: 20px; color: #666; font-size: 14px;">These details will help verify ownership when someone claims this item.</p>
            
            <div class="form-group">
                <label for="color">Color <span style="color: red;">*</span></label>
                <input type="text" id="color" name="color" required placeholder="e.g., Black, Red, Blue">
            </div>
            
            <div class="form-group">
                <label for="brand">Brand (if applicable)</label>
                <input type="text" id="brand" name="brand" placeholder="e.g., Apple, Samsung, Nike">
            </div>
            
            <div class="form-group">
                <label for="distinguishing_features">Distinguishing Features <span style="color: red;">*</span></label>
                <textarea id="distinguishing_features" name="distinguishing_features" rows="3" required placeholder="Describe any scratches, engravings, stickers, or unique features"></textarea>
            </div>
            
            <div class="form-group">
                <label for="approximate_value">Approximate Value</label>
                <input type="text" id="approximate_value" name="approximate_value" placeholder="e.g., $50, 5000 BDT">
            </div>
            
            <div class="form-group">
                <label for="item_image">Item Image</label>
                <input type="file" id="item_image" name="item_image" accept="image/*">
                <small style="color: #666; font-size: 12px;">Upload JPG, JPEG, PNG, or GIF files (max 5MB)</small>
            </div>

            <button type="submit"><i class="fas fa-plus-circle"></i> Submit Found Item</button>
        </form>
    </div>
</body>
</html>
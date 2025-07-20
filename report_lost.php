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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category_id = intval($_POST['category_id']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    $date_lost = $_POST['date_lost'];

    
    // Handle image upload
    $image_url = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
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

    if ($item_name && $category_id && $location && $date_lost) {
        // Insert lost item
        $sql = "INSERT INTO items (User_ID, Item_Name, Item_Type, Category_ID, Location, Description, Date_Lost_Found, Status, Image_URL) VALUES (?, ?, 'lost', ?, ?, ?, ?, 'open', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssiss', $user_id, $item_name, $category_id, $location, $description, $date_lost, $image_url);
        if ($stmt->execute()) {
            $item_id = $stmt->insert_id;
            // Notify all users and admins except the reporter
            $notif_users_sql = "SELECT User_ID FROM user WHERE User_ID != ?";
            $notif_stmt = $conn->prepare($notif_users_sql);
            $notif_stmt->bind_param('s', $user_id);
            $notif_stmt->execute();
            $notif_result = $notif_stmt->get_result();
            $notif_msg = "New lost item reported: $item_name";
            while ($notif_row = $notif_result->fetch_assoc()) {
                $notif_user_id = $notif_row['User_ID'];
                $notif_insert_sql = "INSERT INTO notifications (User_ID, Item_ID, Type, Message) VALUES (?, ?, 'system', ?)";
                $notif_insert_stmt = $conn->prepare($notif_insert_sql);
                $notif_insert_stmt->bind_param('sis', $notif_user_id, $item_id, $notif_msg);
                $notif_insert_stmt->execute();
            }
            $success_message = "Lost item reported successfully!";
        } else {
            $error_message = "Error reporting lost item. Please try again.";
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
    <title>Report Lost Item</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: 'Merriweather', serif; background: #f5f5f5; margin: 0; padding: 0; }
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
    <div class="container">
        <h1>Report Lost Item</h1>
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
                <label for="date_lost">Date Lost *</label>
                <input type="date" id="date_lost" name="date_lost" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="item_image">Item Image</label>
                <input type="file" id="item_image" name="item_image" accept="image/*">
                <small style="color: #666; font-size: 12px;">Upload JPG, JPEG, PNG, or GIF files (max 5MB)</small>
            </div>

            <button type="submit"><i class="fas fa-plus-circle"></i> Submit Lost Item</button>
        </form>
    </div>
</body>
</html> 
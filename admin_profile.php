<?php
require_once 'config.php';
session_start();

// Redirect to admin login if not authenticated as admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$success_message = $error_message = '';

// Get admin details
try {
    $admin_sql = "SELECT * FROM user WHERE User_ID = ? AND Role = 'admin'";
    $admin_stmt = executeQuery($conn, $admin_sql, [$admin_id], 's');
    $admin = fetchOne($admin_stmt);
    
    if (!$admin) {
        header("Location: admin_login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Admin profile error: " . $e->getMessage());
    $error_message = "Error loading admin profile.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contact_no = trim($_POST['contact_no'] ?? '');
        
        if (empty($name) || empty($email)) {
            $error_message = "Name and email are required fields.";
        } else {
            try {
                // Check if email is already taken by another user
                $email_check_sql = "SELECT User_ID FROM user WHERE Email = ? AND User_ID != ?";
                $email_check_stmt = executeQuery($conn, $email_check_sql, [$email, $admin_id], 'ss');
                $existing_user = fetchOne($email_check_stmt);
                
                if ($existing_user) {
                    $error_message = "Email address is already in use by another user.";
                } else {
                    // Update admin profile
                    $update_sql = "UPDATE user SET Name = ?, Email = ?, Contact_No = ? WHERE User_ID = ?";
                    executeQuery($conn, $update_sql, [$name, $email, $contact_no, $admin_id], 'ssss');
                    
                    // Update session
                    $_SESSION['name'] = $name;
                    
                    $success_message = "Profile updated successfully!";
                    
                    // Refresh admin data
                    $admin_stmt = executeQuery($conn, $admin_sql, [$admin_id], 's');
                    $admin = fetchOne($admin_stmt);
                }
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error_message = "Error updating profile. Please try again.";
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirm password do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            try {
                // Verify current password
                if (password_verify($current_password, $admin['Password'])) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $password_sql = "UPDATE user SET Password = ? WHERE User_ID = ?";
                    executeQuery($conn, $password_sql, [$hashed_password, $admin_id], 'ss');
                    
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (Exception $e) {
                error_log("Password change error: " . $e->getMessage());
                $error_message = "Error changing password. Please try again.";
            }
        }
    } elseif ($action === 'upload_photo') {
        // Handle profile photo upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            
            // Create uploads directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = 'admin_' . $admin_id . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    try {
                        // Update admin profile with photo URL
                        $photo_sql = "UPDATE user SET Profile_Photo = ? WHERE User_ID = ?";
                        executeQuery($conn, $photo_sql, [$upload_path, $admin_id], 'ss');
                        
                        $success_message = "Profile photo uploaded successfully!";
                        
                        // Refresh admin data
                        $admin_stmt = executeQuery($conn, $admin_sql, [$admin_id], 's');
                        $admin = fetchOne($admin_stmt);
                    } catch (Exception $e) {
                        error_log("Profile photo update error: " . $e->getMessage());
                        $error_message = "Error updating profile photo. Please try again.";
                    }
                } else {
                    $error_message = "Failed to upload photo. Please try again.";
                }
            } else {
                $error_message = "Invalid file type. Please upload JPG, JPEG, PNG, or GIF files only.";
            }
        } else {
            $error_message = "Please select a photo to upload.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Profile | BRAC UNIVERSITY LOST & FOUND</title>
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
        .container { max-width: 800px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #009688; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; }
        input, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        button { background: #009688; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #00796b; }
        .success { background: #e0f7fa; color: #00796B; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .section { margin-bottom: 40px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .section h2 { color: #009688; margin-bottom: 20px; }
        .profile-photo { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #009688; margin: 0 auto 20px; display: block; }
        .profile-photo-placeholder { width: 150px; height: 150px; border-radius: 50%; background: #f0f0f0; border: 4px solid #009688; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #999; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Admin Profile</div>
        <div class="nav-menu">
            <a href="admin_home.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin_review_claims.php" class="nav-item"><i class="fas fa-hand-paper"></i> Review Claims</a>
            <a href="admin_review_items.php" class="nav-item"><i class="fas fa-clipboard-check"></i> Review Items</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <h1><i class="fas fa-user-shield"></i> Admin Profile</h1>
        
        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Profile Photo Section -->
        <div class="section">
            <h2><i class="fas fa-camera"></i> Profile Photo</h2>
            <?php if (!empty($admin['Profile_Photo'])): ?>
                <img src="<?php echo htmlspecialchars($admin['Profile_Photo']); ?>" alt="Admin Profile" class="profile-photo">
            <?php else: ?>
                <div class="profile-photo-placeholder">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_photo">
                <div class="form-group">
                    <label>Upload New Photo:</label>
                    <input type="file" name="profile_photo" accept="image/*" required>
                </div>
                <button type="submit"><i class="fas fa-upload"></i> Upload Photo</button>
            </form>
        </div>

        <!-- Profile Information -->
        <div class="section">
            <h2><i class="fas fa-user"></i> Profile Information</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Full Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($admin['Name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin['Email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Number:</label>
                    <input type="text" name="contact_no" value="<?php echo htmlspecialchars($admin['Contact_No'] ?? ''); ?>">
                </div>
                
                <button type="submit"><i class="fas fa-save"></i> Update Profile</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="section">
            <h2><i class="fas fa-lock"></i> Change Password</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Current Password:</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>New Password:</label>
                    <input type="password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password:</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit"><i class="fas fa-key"></i> Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>

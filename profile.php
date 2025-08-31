<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

try {
    // Get user information
    $sql = "SELECT * FROM user WHERE User_ID = ?";
    $stmt = executeQuery($conn, $sql, [$user_id], 's');
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Handle profile update
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = trim($_POST['name']);
        $contact_no = trim($_POST['contact_no']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate contact number
        if (!preg_match("/^01[3-9]\d{8}$/", $contact_no)) {
            throw new Exception("Please enter a valid Bangladeshi phone number");
        }

        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update name and contact number
            $update_sql = "UPDATE user SET Name = ?, Contact_No = ? WHERE User_ID = ?";
            executeQuery($conn, $update_sql, [$name, $contact_no, $user_id], 'sss');
            
            // If password change is requested
            if (!empty($current_password)) {
                if (password_verify($current_password, $user['Password'])) {
                    if ($new_password === $confirm_password) {
                        if (strlen($new_password) >= 8 && 
                            preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $new_password)) {
                            
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_password = "UPDATE user SET Password = ? WHERE User_ID = ?";
                            executeQuery($conn, $update_password, [$hashed_password, $user_id], 'ss');
                        } else {
                            throw new Exception("New password does not meet requirements");
                        }
                    } else {
                        throw new Exception("New passwords do not match");
                    }
                } else {
                    throw new Exception("Current password is incorrect");
                }
            }
            
            $conn->commit();
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = executeQuery($conn, $sql, [$user_id], 's');
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - My Profile</title>
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
        .top-bar, .container, .profile-card {
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
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 1px;
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
            background-color: rgba(255,255,255,0.1);
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .profile-card {
            background-color: rgba(255,255,255,0.98);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-family: 'Merriweather', serif;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background-color: #e0f7fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
            color: var(--primary-color);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: 'Merriweather', serif;
        }
        input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s;
            font-family: 'Merriweather', serif;
            font-weight: 700;
        }
        .btn:hover {
            background-color: var(--primary-dark);
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .error {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        .password-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .password-section h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .password-requirements ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        .password-requirements li {
            margin: 3px 0;
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
            }
            .container {
                padding: 0 5px;
            }
            .profile-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo"><i class="fas fa-tree"></i> BRAC UNIVERSITY LOST & FOUND</div>
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
            <a href="notifications.php" class="nav-item">
                <i class="fas fa-bell"></i> Notifications
                <?php if (isset($notifications) && $notifications > 0): ?>
                    <span class="notification-badge"><?php echo $notifications; ?></span>
                <?php endif; ?>
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
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h2>My Profile</h2>
            </div>

            <?php if ($success_message): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return validateForm()">
                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['User_ID']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['Email']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['Name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_no" value="<?php echo htmlspecialchars($user['Contact_No']); ?>" 
                           pattern="01[3-9]\d{8}" required>
                </div>

                <div class="password-section">
                    <h3>Change Password</h3>
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password">
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="8">
                        <div class="password-requirements">
                            Password must:
                            <ul>
                                <li>Be at least 8 characters long</li>
                                <li>Contain at least one uppercase letter</li>
                                <li>Contain at least one lowercase letter</li>
                                <li>Contain at least one number</li>
                                <li>Contain at least one special character</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>
    </div>

    <script>
    function validateForm() {
        const currentPassword = document.querySelector('input[name="current_password"]').value;
        const newPassword = document.querySelector('input[name="new_password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        // If any password field is filled, validate all password fields
        if (currentPassword || newPassword || confirmPassword) {
            if (!currentPassword) {
                alert("Please enter your current password");
                return false;
            }
            
            if (!newPassword) {
                alert("Please enter a new password");
                return false;
            }
            
            if (!confirmPassword) {
                alert("Please confirm your new password");
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert("New passwords do not match!");
                return false;
            }
            
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!passwordRegex.test(newPassword)) {
                alert("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character");
                return false;
            }
        }
        
        return true;
    }
    </script>
</body>
</html>
<?php
require_once 'config.php';
session_start();

// Redirect to admin dashboard if already logged in as admin
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header("Location: admin_home.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$login_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $login_error = "Invalid request!";
    } else {
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        // Validate BRAC email format
        if (!preg_match("/^[a-z]+@bracu\.ac\.bd$/", $email)) {
            $login_error = "Please use a valid BRAC University email";
        } else {
            try {
                // Check if email exists and is admin
                $sql = "SELECT User_ID, Email, Password, Name, 
                               Contact_No, Login_Attempts, Last_Login_Attempt,
                               Account_Status, Role
                        FROM user
                        WHERE Email = ? AND Role = 'admin'";
                
                $stmt = executeQuery($conn, $sql, [$email], 's');
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if account is active
                    if ($user['Account_Status'] !== 'active') {
                        $login_error = "Your admin account is not active. Please contact support.";
                    }
                    // Check for too many login attempts
                    else if ($user['Login_Attempts'] >= 5 && 
                        strtotime($user['Last_Login_Attempt']) > strtotime('-15 minutes')) {
                        $login_error = "Too many login attempts. Please try again in 15 minutes.";
                    } else {
                        // Verify password
                        if (password_verify($password, $user['Password'])) {
                            // Reset login attempts on successful login
                            $reset_attempts = "UPDATE user SET Login_Attempts = 0, Last_Login = NOW() WHERE User_ID = ?";
                            executeQuery($conn, $reset_attempts, [$user['User_ID']], 's');
                            
                            // Log admin login
                            $log_sql = "INSERT INTO admin_logs (Admin_ID, Action, Description) VALUES (?, 'login', 'Admin logged in successfully')";
                            executeQuery($conn, $log_sql, [$user['User_ID']], 's');
                            
                            // Regenerate session ID for security
                            session_regenerate_id(true);
                            
                            // Set session variables
                            $_SESSION['user_id'] = $user['User_ID'];
                            $_SESSION['email'] = $user['Email'];
                            $_SESSION['name'] = $user['Name'];
                            $_SESSION['contact_no'] = $user['Contact_No'];
                            $_SESSION['role'] = 'admin';
                            $_SESSION['last_activity'] = time();
                            $_SESSION['is_admin'] = true;
                            
                            // Log successful login
                            error_log("Successful admin login for user: " . $user['Email']);
                            
                            // Redirect to admin dashboard
                            header("Location: admin_home.php");
                            exit();
                        } else {
                            // Increment login attempts
                            $attempts = $user['Login_Attempts'] + 1;
                            $update_attempts = "UPDATE user SET Login_Attempts = ?, Last_Login_Attempt = NOW() WHERE User_ID = ?";
                            executeQuery($conn, $update_attempts, [$attempts, $user['User_ID']], 'is');
                            
                            // Log failed attempt
                            error_log("Failed admin login attempt for user: " . $user['Email']);
                            
                            $login_error = "Invalid password!";
                        }
                    }
                } else {
                    $login_error = "Admin account not found or insufficient privileges!";
                }
            } catch (Exception $e) {
                error_log("Admin login error: " . $e->getMessage());
                $login_error = "An error occurred. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Admin Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Merriweather', serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            margin: 2rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .admin-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            color: #222;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
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
            justify-content: center;
            gap: 0.5rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 150, 136, 0.3);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .security-notice {
            background: #e3f2fd;
            color: #1565c0;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="admin-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">BRAC UNIVERSITY Lost & Found System</p>
        </div>

        <?php if (!empty($login_error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i> Admin Email
                </label>
                <input type="email" id="email" name="email" class="form-input" 
                       required placeholder="admin@bracu.ac.bd"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" class="form-input" 
                       required placeholder="Enter your password">
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Login as Admin
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                Back to User Login
            </a>
        </div>

        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <strong>Security Notice:</strong> This is a restricted admin area. 
            Unauthorized access attempts will be logged.
        </div>
    </div>
</body>
</html>

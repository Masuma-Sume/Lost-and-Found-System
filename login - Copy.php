<?php
include 'config.php';
session_start();

// Redirect to home if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
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
        if (!preg_match("/^[a-z]+\.[0-9]{4}@bracu\.ac\.bd$/", $email)) {
            $login_error = "Please use a valid BRAC University email";
        } else {
            try {
                // Check if email exists
                $sql = "SELECT User_ID, Email, Password, Name, 
                               Contact_No, Login_Attempts, Last_Login_Attempt,
                               Account_Status
                        FROM user
                        WHERE Email = ?";
                
                $stmt = executeQuery($conn, $sql, [$email], 's');
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if account is active
                    if ($user['Account_Status'] !== 'active') {
                        $login_error = "Your account is not active. Please contact support.";
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
                            
                            // Regenerate session ID for security
                            session_regenerate_id(true);
                            
                            // Set session variables
                            $_SESSION['user_id'] = $user['User_ID'];
                            $_SESSION['email'] = $user['Email'];
                            $_SESSION['name'] = $user['Name'];
                            $_SESSION['contact_no'] = $user['Contact_No'];
                            $_SESSION['last_activity'] = time();
                            
                            // Log successful login
                            error_log("Successful login for user: " . $user['Email']);
                            
                            // Redirect to home page
                            header("Location: home.php");
                            exit();
                        } else {
                            // Increment login attempts
                            $attempts = $user['Login_Attempts'] + 1;
                            $update_attempts = "UPDATE user SET Login_Attempts = ?, Last_Login_Attempt = NOW() WHERE User_ID = ?";
                            executeQuery($conn, $update_attempts, [$attempts, $user['User_ID']], 'is');
                            
                            // Log failed attempt
                            error_log("Failed login attempt for user: " . $user['Email']);
                            
                            $login_error = "Invalid password!";
                        }
                    }
                } else {
                    $login_error = "Email not found!";
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $login_error = "An error occurred. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Lost & Found Login</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            min-height: 100vh;
        }

        .image-section {
            flex: 1;
            background-image: url('image.jpg');
            background-size: cover;
            background-position: center;
        }

        .login-section {
            width: 400px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        h1 {
            color: #0066cc;
            margin-bottom: 5px;
        }

        h2 {
            color: #333;
            margin-top: 0;
            margin-bottom: 30px;
        }

        .login-box {
            background-color: #e6f2ff;
            padding: 30px;
            border-radius: 8px;
        }

        h3 {
            color: #0066cc;
            margin-top: 0;
            text-align: center;
        }

        .input-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0052a3;
        }

        .links {
            margin-top: 20px;
            text-align: center;
        }

        .links a {
            color: #0066cc;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .error {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="image-section"></div>
    
    <div class="login-section">
        <div class="login-box">
            <h3>Login</h3>
            
            <?php if ($login_error): ?>
                <div class="error"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <label>BRAC Email</label>
                    <input type="email" name="email" placeholder="xyz.2020@bracu.ac.bd" required>
                </div>
                
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <button type="submit">Login</button>
            </form>
        </div>
        
        <div class="links">
            <p>Lost something? Found something? <a href="report.php">Click here</a></p>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="home.php">Return to Home</a></p>
        </div>
    </div>
</body>
</html>

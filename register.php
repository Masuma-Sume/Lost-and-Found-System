<?php
require_once 'config.php';
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request!";
    } else {
        try {
            // Sanitize and validate inputs (using modern methods)
            $name = trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'));
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $student_id = trim(htmlspecialchars($_POST['student_id'], ENT_QUOTES, 'UTF-8'));
            $contact_no = trim(htmlspecialchars($_POST['contact_no'], ENT_QUOTES, 'UTF-8'));
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate BRAC email format
            if (!preg_match("/^[a-z]+@bracu\.ac\.bd$/", $email)) {
                $error = "Please use a valid BRAC University email (e.g., xyz@bracu.ac.bd)";
            }
            // Validate Student ID format
            elseif (!preg_match("/^[0-9]{7}$/", $student_id)) {
                $error = "Please enter a valid 7-digit Student ID";
            }
            // Validate Bangladesh phone number
            elseif (!preg_match("/^01[3-9]\d{8}$/", $contact_no)) {
                $error = "Please enter a valid Bangladeshi phone number (e.g., 01712345678)";
            }
            // Check if passwords match
            elseif ($password !== $confirm_password) {
                $error = "Passwords do not match!";
            }
            // Check password strength
            elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long";
            }
            // Check for password complexity
            elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
                $error = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character";
            } else {
                // Check if email or student ID already exists
                $check_query = "SELECT * FROM user WHERE Email = ? OR User_ID = ?";
                $stmt = executeQuery($conn, $check_query, [$email, $student_id], 'ss');
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Email or Student ID already registered";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                                                 // Insert into user table with contact number
                         $user_query = "INSERT INTO user (User_ID, Email, Name, Password, Contact_No, Role, Login_Attempts, Account_Status) 
                                       VALUES (?, ?, ?, ?, ?, 'user', 0, 'active')";
                         $stmt = executeQuery($conn, $user_query, [$student_id, $email, $name, $hashed_password, $contact_no], 'sssss');
                        
                                                 // Create welcome notification
                         $notification_query = "INSERT INTO notifications (User_ID, Type, Message) 
                                              VALUES (?, 'system', 'Welcome to BRAC University Lost & Found System!')";
                         $stmt = executeQuery($conn, $notification_query, [$student_id], 's');
                        
                        // Commit transaction
                        $conn->commit();
                        
                        // Log successful registration
                        error_log("New user registered: " . $email);
                        
                        $success = "Registration successful! You can now login.";
                        
                        // Clear form data
                        $_POST = array();
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Registration error: " . $e->getMessage());
                        $error = "Registration failed: " . $e->getMessage();
                    }   
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Lost & Found Registration</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .register-container {
            background-color: #e6f2ff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 400px;
            padding: 30px;
        }
        
        h1 {
            color: #0066cc;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
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
            font-family: "Times New Roman", Times, serif;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: "Times New Roman", Times, serif;
            font-size: 16px;
            margin-top: 10px;
        }
        
        button:hover {
            background-color: #0052a3;
        }
        
        .error {
            color: #d32f2f;
            text-align: center;
            margin: 15px 0;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            text-align: center;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #0066cc;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 5px rgba(0,102,204,0.3);
        }
        
        .form-group input.error {
            border-color: #d32f2f;
        }
        
        .form-group .error-message {
            color: #d32f2f;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .password-requirements li {
            transition: all 0.3s ease;
            padding: 2px 0;
        }
        
        .password-requirements li.valid {
            color: #28a745;
            text-decoration: line-through;
            opacity: 0.7;
        }
        
        .password-requirements li.invalid {
            color: #666;
        }
        
        .password-strength {
            margin-top: 10px;
            height: 5px;
            background-color: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>Lost & Found System Registration</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>BRAC Email (e.g., xyz@bracu.ac.bd)</label>
                <input type="email" name="email" pattern="[a-z]+@bracu\.ac\.bd" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Student ID (7 digits)</label>
                <input type="text" name="student_id" pattern="[0-9]{7}" 
                       value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Contact Number (e.g., 01712345678)</label>
                <input type="text" name="contact_no" pattern="01[3-9]\d{8}" 
                       value="<?php echo isset($_POST['contact_no']) ? htmlspecialchars($_POST['contact_no']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" minlength="8" required oninput="checkPasswordStrength(this.value)">
                <div class="password-requirements">
                    Password must:
                    <ul>
                        <li id="length-req">Be at least 8 characters long</li>
                        <li id="uppercase-req">Contain at least one uppercase letter</li>
                        <li id="lowercase-req">Contain at least one lowercase letter</li>
                        <li id="number-req">Contain at least one number</li>
                        <li id="special-req">Contain at least one special character</li>
                    </ul>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strength-bar"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" minlength="8" required>
            </div>
            
            <button type="submit">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
    function checkPasswordStrength(password) {
        // Get requirement elements
        const lengthReq = document.getElementById('length-req');
        const uppercaseReq = document.getElementById('uppercase-req');
        const lowercaseReq = document.getElementById('lowercase-req');
        const numberReq = document.getElementById('number-req');
        const specialReq = document.getElementById('special-req');
        const strengthBar = document.getElementById('strength-bar');
        
        // Check each requirement
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[@$!%*?&]/.test(password);
        
        // Update requirement display
        updateRequirement(lengthReq, hasLength);
        updateRequirement(uppercaseReq, hasUppercase);
        updateRequirement(lowercaseReq, hasLowercase);
        updateRequirement(numberReq, hasNumber);
        updateRequirement(specialReq, hasSpecial);
        
        // Calculate strength
        const validRequirements = [hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial].filter(Boolean).length;
        const strengthPercentage = (validRequirements / 5) * 100;
        
        // Update strength bar
        strengthBar.style.width = strengthPercentage + '%';
        
        if (strengthPercentage <= 40) {
            strengthBar.className = 'password-strength-bar strength-weak';
        } else if (strengthPercentage <= 80) {
            strengthBar.className = 'password-strength-bar strength-medium';
        } else {
            strengthBar.className = 'password-strength-bar strength-strong';
        }
    }
    
    function updateRequirement(element, isValid) {
        if (isValid) {
            element.className = 'valid';
        } else {
            element.className = 'invalid';
        }
    }
    
    function validateForm() {
        const password = document.querySelector('input[name="password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirmPassword) {
            alert("Passwords do not match!");
            return false;
        }
        
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        if (!passwordRegex.test(password)) {
            alert("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character");
            return false;
        }
        
        return true;
    }
    </script>
</body>
</html>
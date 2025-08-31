<?php
session_start();
require 'dbconnection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check in User table
    $user_stmt = $conn->prepare("SELECT User_ID, Name, Password FROM User WHERE Email = ?");
    $user_stmt->bind_param("s", $email);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    // Check in Admin table
    $admin_stmt = $conn->prepare("SELECT Admin_ID, Name, Password FROM Admin WHERE Email = ?");
    $admin_stmt->bind_param("s", $email);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    if ($user_result->num_rows === 1) {
        $user = $user_result->fetch_assoc();
        if (password_verify($password, $user['Password'])) {
            $_SESSION['user_id'] = $user['User_ID'];
            $_SESSION['user_name'] = $user['Name'];
            $_SESSION['user_type'] = 'user';
            header('Location: home.php');
            exit();
        } else {
            $error = "Incorrect password for user.";
        }
    } elseif ($admin_result->num_rows === 1) {
        $admin = $admin_result->fetch_assoc();
        if (password_verify($password, $admin['Password'])) {
            $_SESSION['user_id'] = $admin['Admin_ID'];
            $_SESSION['user_name'] = $admin['Name'];
            $_SESSION['user_type'] = 'admin';
            header('Location: admin_home.php');
            exit();
        } else {
            $error = "Incorrect password for admin.";
        }
    } else {
        $error = "No account found with that email.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - BRAC Lost & Found</title>
    <style>
        body {
            font-family: Arial;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            padding-top: 100px;
        }
        .login-container {
            background: #fff;
            padding: 25px 30px;
            box-shadow: 0px 0px 10px #ccc;
            border-radius: 10px;
            width: 300px;
        }
        .login-container h1 {
            font-size: 22px;
            margin-bottom: 20px;
            text-align: center;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            margin-bottom: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .error {
            color: red;
            font-size: 14px;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>BRAC Lost & Found</h1>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="email" placeholder="Email (for user/admin)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>

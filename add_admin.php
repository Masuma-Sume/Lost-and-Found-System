<?php
require_once 'config.php';

echo "<h2>Add Admin User</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    if (empty($email)) {
        echo "<p style='color: red;'>❌ Please enter a valid email address.</p>";
    } else {
        try {
            // Check if user exists
            $user_check = executeQuery($conn, "SELECT User_ID, Name, Role FROM user WHERE Email = ?", [$email], 's');
            $user_result = $user_check->get_result();
            
            if ($user_result->num_rows == 0) {
                echo "<p style='color: red;'>❌ User with email '$email' not found.</p>";
            } else {
                $user = $user_result->fetch_assoc();
                
                if ($user['Role'] === 'admin') {
                    echo "<p style='color: orange;'>⚠️ User '{$user['Name']}' is already an admin.</p>";
                } else {
                    // Update user to admin
                    executeQuery($conn, "UPDATE user SET Role = 'admin' WHERE Email = ?", [$email], 's');
                    echo "<p style='color: green;'>✅ Successfully made '{$user['Name']}' an admin!</p>";
                    echo "<p>User ID: {$user['User_ID']}</p>";
                    echo "<p>Email: $email</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Show current admin users
echo "<h3>Current Admin Users:</h3>";
try {
    $admins = executeQuery($conn, "SELECT User_ID, Name, Email FROM user WHERE Role = 'admin'", [], '');
    $admin_result = $admins->get_result();
    
    if ($admin_result->num_rows > 0) {
        echo "<ul>";
        while ($admin = $admin_result->fetch_assoc()) {
            echo "<li>{$admin['Name']} ({$admin['Email']}) - {$admin['User_ID']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No admin users found.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading admin users: " . $e->getMessage() . "</p>";
}
?>

<form method="POST" style="margin: 20px 0; padding: 20px; border: 1px solid #ccc; border-radius: 10px;">
    <h3>Add New Admin</h3>
    <p><strong>Enter the email address of the user you want to make an admin:</strong></p>
    <input type="email" name="email" placeholder="user@bracu.ac.bd" required style="padding: 10px; width: 300px; margin: 10px 0;">
    <br>
    <button type="submit" style="padding: 10px 20px; background: #009688; color: white; border: none; border-radius: 5px; cursor: pointer;">Make Admin</button>
</form>

<p><strong>Instructions:</strong></p>
<ol>
    <li>Enter the email address of the user you want to make an admin</li>
    <li>Click "Make Admin"</li>
    <li>The user will now be able to login at <a href="admin_login.php">admin_login.php</a></li>
    <li>They can use their existing password to login</li>
</ol>

<p><a href="admin_login.php">← Back to Admin Login</a></p>

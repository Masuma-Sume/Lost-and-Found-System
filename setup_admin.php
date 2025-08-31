<?php
require_once 'config.php';

echo "<h2>Admin Setup</h2>";

try {
    // Check if admin user exists
    $admin_check = executeQuery($conn, "SELECT User_ID, Email, Name, Role FROM user WHERE Role = 'admin'", [], '');
    $admin_result = $admin_check->get_result();
    
    if ($admin_result->num_rows > 0) {
        echo "<p>✅ Admin user(s) found:</p>";
        while ($admin = $admin_result->fetch_assoc()) {
            echo "<p>- {$admin['Name']} ({$admin['Email']}) - {$admin['User_ID']}</p>";
        }
    } else {
        echo "<p>❌ No admin user found. Creating default admin...</p>";
        
        // Create default admin user
        $admin_id = 'ADM001';
        $admin_email = 'admin@bracu.ac.bd';
        $admin_name = 'System Admin';
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        
        $insert_admin = "INSERT INTO user (User_ID, Email, Name, Password, Role, Account_Status) 
                        VALUES (?, ?, ?, ?, 'admin', 'active')";
        executeQuery($conn, $insert_admin, [$admin_id, $admin_email, $admin_name, $admin_password], 'ssss');
        
        echo "<p>✅ Default admin user created:</p>";
        echo "<p>- Email: $admin_email</p>";
        echo "<p>- Password: admin123</p>";
        echo "<p>- User ID: $admin_id</p>";
    }
    
    // Ensure admin_logs table exists
    $logs_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($logs_check->num_rows == 0) {
        echo "<p>❌ Admin logs table not found. Creating...</p>";
        
        $create_logs = "CREATE TABLE IF NOT EXISTS admin_logs (
            Log_ID INT AUTO_INCREMENT PRIMARY KEY,
            Admin_ID VARCHAR(7) NOT NULL,
            Action VARCHAR(50) NOT NULL,
            Description TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Admin_ID) REFERENCES user(User_ID) ON DELETE CASCADE
        )";
        
        $conn->query($create_logs);
        echo "<p>✅ Admin logs table created.</p>";
    } else {
        echo "<p>✅ Admin logs table exists.</p>";
    }
    
    // Ensure Approval_Status column exists in items table
    $approval_check = $conn->query("SHOW COLUMNS FROM items LIKE 'Approval_Status'");
    if ($approval_check->num_rows == 0) {
        echo "<p>❌ Approval_Status column not found. Adding...</p>";
        
        $add_approval = "ALTER TABLE items ADD COLUMN Approval_Status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER Status";
        $conn->query($add_approval);
        echo "<p>✅ Approval_Status column added to items table.</p>";
    } else {
        echo "<p>✅ Approval_Status column exists in items table.</p>";
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p>You can now access the admin panel at: <a href='admin_login.php'>admin_login.php</a></p>";
    echo "<p>Default admin credentials: admin@bracu.ac.bd / admin123</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error during setup: " . $e->getMessage() . "</p>";
}
?>

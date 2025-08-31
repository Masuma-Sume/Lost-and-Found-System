<?php
// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Create connection without database
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h1>Database Setup - BRAC UNIVERSITY Lost & Found</h1>";
    echo "<p>✅ Database connection successful</p>";
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS lost_and_found";
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }
    echo "<p>✅ Database 'lost_and_found' created/verified</p>";
    
    // Select the database
    $conn->select_db("lost_and_found");
    echo "<p>✅ Database selected</p>";
    
    // Read and execute the setup_database.sql file
    $sql_file = 'setup_database.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("SQL file not found: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    if (!$sql) {
        throw new Exception("Could not read SQL file: $sql_file");
    }
    
    echo "<p>✅ SQL file loaded</p>";
    
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }
    
    if ($conn->error) {
        throw new Exception("Error executing SQL: " . $conn->error);
    }
    
    echo "<p>✅ Database tables created successfully!</p>";
    
    // Verify tables were created
    $tables = ['user', 'categories', 'items', 'notifications', 'claims', 'security_questions', 'badges', 'rewards', 'user_rewards'];
    echo "<h2>Verifying Tables:</h2>";
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<p>✅ Table '$table' exists</p>";
        } else {
            echo "<p>❌ Table '$table' missing</p>";
        }
    }
    
    // Insert some sample categories
    $categories = ['Electronics', 'Books', 'Clothing', 'Accessories', 'Documents', 'Other'];
    echo "<h2>Adding Sample Categories:</h2>";
    
    foreach ($categories as $category) {
        $check_sql = "SELECT Category_ID FROM categories WHERE Category_Name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $insert_sql = "INSERT INTO categories (Category_Name) VALUES (?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param('s', $category);
            if ($stmt->execute()) {
                echo "<p>✅ Added category: $category</p>";
            } else {
                echo "<p>❌ Failed to add category: $category</p>";
            }
        } else {
            echo "<p>ℹ️ Category already exists: $category</p>";
        }
    }
    
    // Insert some sample badges
    $badges = [
        ['Super Helper', 'Awarded for helping find 5 items'],
        ['Community Hero', 'Awarded for helping find 10 items'],
        ['First Timer', 'Awarded for your first found item']
    ];
    
    echo "<h2>Adding Sample Badges:</h2>";
    foreach ($badges as $badge) {
        $check_sql = "SELECT Badge_ID FROM badges WHERE Badge_Name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param('s', $badge[0]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $insert_sql = "INSERT INTO badges (Badge_Name, Badge_Description) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param('ss', $badge[0], $badge[1]);
            if ($stmt->execute()) {
                echo "<p>✅ Added badge: {$badge[0]}</p>";
            } else {
                echo "<p>❌ Failed to add badge: {$badge[0]}</p>";
            }
        } else {
            echo "<p>ℹ️ Badge already exists: {$badge[0]}</p>";
        }
    }
    
    echo "<h2>Setup Complete! 🎉</h2>";
    echo "<p>Your database is now ready to use.</p>";
    echo "<p><a href='index.php' style='background: #009688; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Home Page</a></p>";
    echo "<p><a href='debug_notifications.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Notification System</a></p>";
    
} catch (Exception $e) {
    echo "<h1>Setup Error</h1>";
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>XAMPP is running (Apache and MySQL)</li>";
    echo "<li>MySQL service is started</li>";
    echo "<li>You have proper permissions</li>";
    echo "</ul>";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2 { color: #009688; }
p { margin: 10px 0; }
ul { margin: 10px 0; padding-left: 20px; }
</style> 
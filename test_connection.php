<?php
// Simple test file to verify database connection and basic setup
require_once 'config.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    if ($conn->ping()) {
        echo "✅ Database connection successful<br>";
    } else {
        echo "❌ Database connection failed<br>";
    }
    
    // Test if tables exist
    $tables = ['user', 'items', 'notifications', 'categories'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' does not exist<br>";
        }
    }
    
    // Test basic query
    $test_query = "SELECT COUNT(*) as count FROM user";
    $result = $conn->query($test_query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ User table has " . $row['count'] . " records<br>";
    } else {
        echo "❌ Error querying user table<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='index.php'>Go to Home</a> | <a href='login.php'>Login</a> | <a href='register.php'>Register</a>";
?>

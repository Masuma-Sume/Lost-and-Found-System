<?php
echo "<h1>Notifications Debug</h1>";

// Test 1: Check if config file exists
if (file_exists('config.php')) {
    echo "<p>✅ config.php file exists</p>";
} else {
    echo "<p>❌ config.php file missing</p>";
    exit();
}

// Test 2: Try to include config
try {
    require_once 'config.php';
    echo "<p>✅ config.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p>❌ Error loading config.php: " . $e->getMessage() . "</p>";
    exit();
}

// Test 3: Check database connection
try {
    if (isset($conn)) {
        if ($conn->ping()) {
            echo "<p>✅ Database connection successful</p>";
        } else {
            echo "<p>❌ Database connection failed</p>";
        }
    } else {
        echo "<p>❌ Database connection variable not set</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 4: Check if notifications table exists
if (isset($conn)) {
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result && $result->num_rows > 0) {
        echo "<p>✅ notifications table exists</p>";
        
        // Test 5: Check table structure
        $result = $conn->query("DESCRIBE notifications");
        if ($result) {
            echo "<p>✅ notifications table structure:</p>";
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>❌ notifications table missing</p>";
    }
}

// Test 6: Check if executeQuery function exists
if (function_exists('executeQuery')) {
    echo "<p>✅ executeQuery function exists</p>";
} else {
    echo "<p>❌ executeQuery function missing</p>";
}

// Test 7: Check if fetchAll function exists
if (function_exists('fetchAll')) {
    echo "<p>✅ fetchAll function exists</p>";
} else {
    echo "<p>❌ fetchAll function missing</p>";
}

// Test 8: Test a simple query
if (isset($conn) && function_exists('executeQuery') && function_exists('fetchAll')) {
    try {
        $test_sql = "SELECT COUNT(*) as count FROM notifications";
        $stmt = executeQuery($conn, $test_sql, [], '');
        $result = fetchAll($stmt);
        echo "<p>✅ Test query successful. Total notifications: " . $result[0]['count'] . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Test query failed: " . $e->getMessage() . "</p>";
    }
}

// Test 9: Check session
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p>✅ User logged in: " . $_SESSION['user_id'] . "</p>";
    
    // Test 10: Try to get user's notifications
    if (isset($conn) && function_exists('executeQuery') && function_exists('fetchAll')) {
        try {
            $user_id = $_SESSION['user_id'];
            $notifications_sql = "SELECT n.*, i.Item_Name, i.Item_Type 
                                 FROM notifications n
                                 LEFT JOIN items i ON n.Item_ID = i.Item_ID
                                 WHERE n.User_ID = ?
                                 ORDER BY n.Created_At DESC";
            $stmt = executeQuery($conn, $notifications_sql, [$user_id], 's');
            $notifications = fetchAll($stmt);
            echo "<p>✅ User notifications query successful. Found " . count($notifications) . " notifications</p>";
            
            if (count($notifications) > 0) {
                echo "<p>Sample notification:</p>";
                echo "<pre>" . print_r($notifications[0], true) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p>❌ User notifications query failed: " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<p>ℹ️ No user logged in</p>";
}

echo "<hr>";
echo "<p><a href='notifications.php'>Go to Notifications Page</a></p>";
echo "<p><a href='test_notifications.php'>Test Notifications</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1 { color: #009688; }
p { margin: 10px 0; padding: 5px; }
ul { margin-left: 20px; }
pre { background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
hr { margin: 20px 0; }
</style> 
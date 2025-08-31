<?php
// Include database configuration
$db_config = require 'db_config.php';

try {
    // Create connection without database
    $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['pass']);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Read SQL file
    $sql = file_get_contents('setup_database.sql');
    
    // Execute multi query
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
    
    echo "Database and tables created successfully!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 
<?php
require_once 'config.php';

try {
    // Check if Verification_Answers column exists in claims table
    $result = $conn->query("SHOW COLUMNS FROM claims LIKE 'Verification_Answers'");
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        echo "<p>Verification_Answers column doesn't exist. Adding it now...</p>";
        $conn->query("ALTER TABLE claims ADD COLUMN Verification_Answers TEXT AFTER Claim_Description");
        $conn->query("UPDATE claims SET Verification_Answers = '{}' WHERE Verification_Answers IS NULL");
        echo "<p>Verification_Answers column added successfully!</p>";
    } else {
        echo "<p>Verification_Answers column already exists.</p>";
    }
    
    // Display claims table structure
    echo "<h3>Claims Table Structure:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM claims");
    echo "<pre>";
    while ($column = $columns->fetch_assoc()) {
        print_r($column);
    }
    echo "</pre>";
    
    // Check for any claims with NULL Verification_Answers
    $null_check = $conn->query("SELECT COUNT(*) as count FROM claims WHERE Verification_Answers IS NULL");
    $null_count = $null_check->fetch_assoc()['count'];
    
    if ($null_count > 0) {
        echo "<p>Found {$null_count} claims with NULL Verification_Answers. Updating them...</p>";
        $conn->query("UPDATE claims SET Verification_Answers = '{}' WHERE Verification_Answers IS NULL");
        echo "<p>Updated successfully!</p>";
    } else {
        echo "<p>No claims with NULL Verification_Answers found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
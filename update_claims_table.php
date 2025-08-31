<?php
require_once 'config.php';

try {
    // Read the SQL file
    $sql = file_get_contents('update_claims_table.sql');
    
    // Execute the SQL statements
    if ($conn->multi_query($sql)) {
        echo "<p>Claims table updated successfully!</p>";
        echo "<p>Added Verification_Answers column to the claims table.</p>";
    } else {
        echo "<p>Error updating claims table: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='home.php'>Return to Home</a></p>";
?>
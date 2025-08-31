<?php
require_once 'config.php';

try {
    // Check if Verification_Answers column exists in claims table
    $check_sql = "SHOW COLUMNS FROM claims LIKE 'Verification_Answers'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $alter_sql = "ALTER TABLE claims ADD COLUMN Verification_Answers TEXT AFTER Claim_Description";
        if ($conn->query($alter_sql)) {
            echo "<p>Claims table updated successfully! Added Verification_Answers column.</p>";
            
            // Update existing claims to have an empty JSON object for Verification_Answers
            $update_sql = "UPDATE claims SET Verification_Answers = '{}' WHERE Verification_Answers IS NULL";
            if ($conn->query($update_sql)) {
                echo "<p>Updated existing claims with default Verification_Answers value.</p>";
            } else {
                echo "<p>Error updating existing claims: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>Error updating claims table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>Verification_Answers column already exists in claims table.</p>";
    }
    
    echo "<p>Claims table structure:</p>";
    $table_structure = $conn->query("DESCRIBE claims");
    echo "<pre>";
    while ($row = $table_structure->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin_review_claims.php'>Go to Admin Review Claims</a></p>";
echo "<p><a href='my_claims.php'>Go to My Claims</a></p>";
echo "<p><a href='home.php'>Return to Home</a></p>";
?>
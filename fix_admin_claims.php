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
    
    // Fix any NULL Verification_Answers values
    $null_check = $conn->query("SELECT COUNT(*) as count FROM claims WHERE Verification_Answers IS NULL");
    $null_count = $null_check->fetch_assoc()['count'];
    
    if ($null_count > 0) {
        echo "<p>Found {$null_count} claims with NULL Verification_Answers. Updating them...</p>";
        $conn->query("UPDATE claims SET Verification_Answers = '{}' WHERE Verification_Answers IS NULL");
        echo "<p>Updated successfully!</p>";
    } else {
        echo "<p>No claims with NULL Verification_Answers found.</p>";
    }
    
    // Check for any issues with JSON format in Verification_Answers
    $invalid_json = $conn->query("SELECT Claim_ID, Verification_Answers FROM claims WHERE Verification_Answers IS NOT NULL");
    $fixed_count = 0;
    
    while ($row = $invalid_json->fetch_assoc()) {
        $verification_answers = $row['Verification_Answers'];
        
        // Try to decode JSON
        $decoded = json_decode($verification_answers, true);
        
        // If not valid JSON or empty, fix it
        if ($decoded === null || $verification_answers === '') {
            $conn->query("UPDATE claims SET Verification_Answers = '{}' WHERE Claim_ID = {$row['Claim_ID']}");
            $fixed_count++;
        }
    }
    
    if ($fixed_count > 0) {
        echo "<p>Fixed {$fixed_count} claims with invalid JSON in Verification_Answers.</p>";
    } else {
        echo "<p>All Verification_Answers have valid JSON format.</p>";
    }
    
    echo "<p>Fix completed. <a href='admin_review_claims.php'>Go to Admin Review Claims</a></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
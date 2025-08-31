<?php
require_once 'config.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Verify if the user still exists in database
    try {
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT User_ID FROM user WHERE User_ID = ? AND Account_Status = 'active'";
        $stmt = executeQuery($conn, $sql, [$user_id], 's');
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // User exists and is active, redirect to home
            header("Location: home.php");
            exit();
        } else {
            // User not found or inactive, clear session
            session_destroy();
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Index page error: " . $e->getMessage());
        // On error, redirect to login
        header("Location: login.php");
        exit();
    }
} else {
    // No session, redirect to login
    header("Location: login.php");
    exit();
}
?> 
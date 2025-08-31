<?php
session_start();

// Log the logout action
if (isset($_SESSION['user_id'])) {
    error_log("User logged out: " . $_SESSION['user_id']);
}

// Destroy all session data
session_destroy();

// Clear any session cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header("Location: login.php");
exit();
?> 
<?php
require_once 'config.php';

// Example 1: Select query
function getUserByUsername($username) {
    global $conn;
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = executeQuery($conn, $sql, [$username]);
    return $stmt->get_result()->fetch_assoc();
}

// Example 2: Insert query
function addNewUser($username, $email, $password) {
    global $conn;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = executeQuery($conn, $sql, [$username, $email, $hashedPassword]);
    return $stmt->insert_id;
}

// Example 3: Update query
function updateUserEmail($userId, $newEmail) {
    global $conn;
    $sql = "UPDATE users SET email = ? WHERE id = ?";
    $stmt = executeQuery($conn, $sql, [$newEmail, $userId]);
    return $stmt->affected_rows;
}

// Example 4: Delete query
function deleteUser($userId) {
    global $conn;
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = executeQuery($conn, $sql, [$userId]);
    return $stmt->affected_rows;
}

// Example 5: Select multiple rows
function getAllUsers() {
    global $conn;
    $sql = "SELECT id, username, email FROM users";
    $stmt = executeQuery($conn, $sql);
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Example usage:
try {
    // Get a user
    $user = getUserByUsername('john_doe');
    
    // Add a new user
    $newUserId = addNewUser('jane_doe', 'jane@example.com', 'secure_password');
    
    // Update user email
    $updated = updateUserEmail($newUserId, 'new_email@example.com');
    
    // Get all users
    $allUsers = getAllUsers();
    
    // Delete a user
    $deleted = deleteUser($newUserId);
    
} catch (Exception $e) {
    // Handle any errors
    error_log("Error: " . $e->getMessage());
    echo "An error occurred. Please try again later.";
}
?> 
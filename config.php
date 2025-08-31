<?php
// Only set session settings if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// Load database credentials
$db_config = require_once 'db_config.php';

// Database configuration
$servername = $db_config['host'];
$username = $db_config['user'];
$password = $db_config['pass'];
$dbname = $db_config['name'];

// Create connection
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");
    
    // Set error reporting based on environment
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        error_reporting(0);
        ini_set('display_errors', 0);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
    ini_set('log_errors', 1);
    ini_set('error_log', 'error.log');
    
} catch (Exception $e) {
    // Log error and display user-friendly message
    error_log("Database connection error: " . $e->getMessage());
    die("Sorry, there was a problem connecting to the database. Please try again later.");
}

// Function to prepare and execute queries safely
function executeQuery($conn, $sql, $params = [], $types = '') {
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            // If types are not provided, default to strings
            if (empty($types)) {
                $types = str_repeat('s', count($params));
            }
            
            if (strlen($types) !== count($params)) {
                throw new Exception("Number of parameters does not match types");
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt;
    } catch (Exception $e) {
        error_log("Query execution error: " . $e->getMessage());
        throw new Exception("Database query failed. Please try again later.");
    }
}

// Function to safely fetch all results
function fetchAll($stmt) {
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to safely fetch a single row
function fetchOne($stmt) {
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to send notifications to all users except the sender
function sendNotificationToAllUsers($conn, $sender_id, $item_id, $item_name, $item_type, $location) {
    try {
        // Get all users except the sender
        $users_sql = "SELECT User_ID FROM user WHERE User_ID != ? AND Account_Status = 'active'";
        $users_stmt = executeQuery($conn, $users_sql, [$sender_id], 's');
        $users = fetchAll($users_stmt);
        
        if (empty($users)) {
            return true; // No users to notify
        }
        
        // Prepare the notification message
        $type_text = ($item_type === 'lost') ? 'lost' : 'found';
        $message = "A new {$type_text} item has been reported: \"{$item_name}\" at {$location}";
        
        // Insert notifications for all users
        $notification_sql = "INSERT INTO notifications (User_ID, Item_ID, Type, Message) VALUES (?, ?, 'system', ?)";
        
        $success = true;
        foreach ($users as $user) {
            $user_id = $user['User_ID'];
            try {
                executeQuery($conn, $notification_sql, [$user_id, $item_id, $message], 'sis');
            } catch (Exception $e) {
                error_log("Failed to send notification to user {$user_id}: " . $e->getMessage());
                $success = false;
            }
        }
        
        // Log success for debugging
        error_log("Notifications sent for item {$item_id} to " . count($users) . " users");
        
        return $success;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Function to send notification to specific user
function sendNotificationToUser($conn, $user_id, $item_id, $type, $message) {
    try {
        // If item_id is null, use a different SQL query without the Item_ID field
        if ($item_id === null) {
            $sql = "INSERT INTO notifications (User_ID, Type, Message) VALUES (?, ?, ?)";
            executeQuery($conn, $sql, [$user_id, $type, $message], 'sss');
        } else {
            $sql = "INSERT INTO notifications (User_ID, Item_ID, Type, Message) VALUES (?, ?, ?, ?)";
            executeQuery($conn, $sql, [$user_id, $item_id, $type, $message], 'siss');
        }
        return true;
    } catch (Exception $e) {
        error_log("Single notification error: " . $e->getMessage());
        return false;
    }
}

// Function to get unread notification count for a user
function getUnreadNotificationCount($conn, $user_id) {
    try {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE User_ID = ? AND Is_Read = 0";
        $stmt = executeQuery($conn, $sql, [$user_id], 's');
        $result = fetchOne($stmt);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Notification count error: " . $e->getMessage());
        return 0;
    }
}

// Function to mark notifications as read
function markNotificationsAsRead($conn, $user_id, $notification_ids = null) {
    try {
        if ($notification_ids) {
            // Mark specific notifications as read
            $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
            $sql = "UPDATE notifications SET Is_Read = 1 WHERE User_ID = ? AND Notification_ID IN ($placeholders)";
            $params = array_merge([$user_id], $notification_ids);
            $types = 's' . str_repeat('i', count($notification_ids));
            executeQuery($conn, $sql, $params, $types);
        } else {
            // Mark all notifications as read
            $sql = "UPDATE notifications SET Is_Read = 1 WHERE User_ID = ?";
            executeQuery($conn, $sql, [$user_id], 's');
        }
        return true;
    } catch (Exception $e) {
        error_log("Mark notifications as read error: " . $e->getMessage());
        return false;
    }
}

// Function to calculate similarity percentage between claim answers and item details
function calculateSimilarityPercentage($claim_answers, $item_details) {
    $total_score = 0;
    $max_score = 0;
    
    // Convert claim answers to array if it's JSON
    if (is_string($claim_answers)) {
        $claim_answers = json_decode($claim_answers, true);
    }
    
    // Color similarity (weight: 25%)
    if (!empty($claim_answers['color']) && !empty($item_details['color'])) {
        $color_similarity = similar_text(
            strtolower(trim($claim_answers['color'])), 
            strtolower(trim($item_details['color'])), 
            $color_percent
        );
        $total_score += ($color_percent * 0.25);
        $max_score += 25;
    }
    
    // Brand similarity (weight: 20%)
    if (!empty($claim_answers['brand']) && !empty($item_details['brand'])) {
        $brand_similarity = similar_text(
            strtolower(trim($claim_answers['brand'])), 
            strtolower(trim($item_details['brand'])), 
            $brand_percent
        );
        $total_score += ($brand_percent * 0.20);
        $max_score += 20;
    }
    
    // Distinguishing features similarity (weight: 30%)
    if (!empty($claim_answers['distinguishing_features']) && !empty($item_details['distinguishing_features'])) {
        $features_similarity = similar_text(
            strtolower(trim($claim_answers['distinguishing_features'])), 
            strtolower(trim($item_details['distinguishing_features'])), 
            $features_percent
        );
        $total_score += ($features_percent * 0.30);
        $max_score += 30;
    }
    
    // Value similarity (weight: 15%)
    if (!empty($claim_answers['approximate_value']) && !empty($item_details['approximate_value'])) {
        $value_similarity = similar_text(
            strtolower(trim($claim_answers['approximate_value'])), 
            strtolower(trim($item_details['approximate_value'])), 
            $value_percent
        );
        $total_score += ($value_percent * 0.15);
        $max_score += 15;
    }
    
    // Date similarity (weight: 10%)
    if (!empty($claim_answers['date_lost']) && !empty($item_details['date_lost'])) {
        $claim_date = strtotime($claim_answers['date_lost']);
        $item_date = strtotime($item_details['date_lost']);
        
        if ($claim_date && $item_date) {
            $date_diff = abs($claim_date - $item_date);
            $days_diff = $date_diff / (60 * 60 * 24);
            
            // If dates are within 7 days, give full points, otherwise reduce
            if ($days_diff <= 7) {
                $date_percent = 100;
            } elseif ($days_diff <= 30) {
                $date_percent = 70;
            } elseif ($days_diff <= 90) {
                $date_percent = 40;
            } else {
                $date_percent = 10;
            }
            
            $total_score += ($date_percent * 0.10);
            $max_score += 10;
        }
    }
    
    // Calculate final percentage
    if ($max_score > 0) {
        $final_percentage = round(($total_score / $max_score) * 100);
        return min(100, max(0, $final_percentage)); // Ensure between 0-100
    }
    
    return 0;
}

// Function to update claim verification score
function updateClaimVerificationScore($conn, $claim_id, $score) {
    try {
        $sql = "UPDATE claims SET Verification_Score = ? WHERE Claim_ID = ?";
        executeQuery($conn, $sql, [$score, $claim_id], 'ii');
        return true;
    } catch (Exception $e) {
        error_log("Update verification score error: " . $e->getMessage());
        return false;
    }
}
?>

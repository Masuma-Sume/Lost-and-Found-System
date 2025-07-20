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
?>	 

<?php
// Database credentials - DO NOT commit this file to version control
// Define environment
define('ENVIRONMENT', 'development'); // Change to 'production' in production

// Base configuration
$config = [
    'development' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'lost_and_found'
    ],
    'production' => [
        'host' => 'localhost',
        'user' => '', // Set production credentials
        'pass' => '', // Set production credentials
        'name' => 'lost_and_found'
    ]
];

// Return configuration based on environment
return $config[ENVIRONMENT]; 
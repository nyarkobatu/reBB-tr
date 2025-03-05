<?php
/**
 * reBB - Kernel
 * 
 * This file serves as the application kernel that handles core initialization
 * and configuration loading. It must be included at the beginning of all application
 * entry points.
 */

// Define the base path for the application
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__FILE__));
}

/**
 * Kernel panic function - terminates execution and displays error message
 * 
 * @param string $message The error message to display
 * @return void
 */
function kernel_panic($message) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set HTTP response code
    http_response_code(500);
    
    // Display error message with minimal styling
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>reBB - Kernel Panic</title>
        <style>
            body { 
                font-family: sans-serif; 
                background: #f8f8f8; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
            }
            .panic-container { 
                background: #fff; 
                border-left: 5px solid #dc3545; 
                padding: 30px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                max-width: 600px; 
                width: 100%; 
            }
            h1 { 
                color: #dc3545; 
                margin-top: 0; 
            }
            .message { 
                background: #f8d7da; 
                border: 1px solid #f5c6cb; 
                color: #721c24; 
                padding: 15px; 
                border-radius: 4px; 
                margin: 20px 0; 
            }
            .info {
                font-size: 0.9em;
                color: #6c757d;
            }
        </style>
    </head>
    <body>
        <div class="panic-container">
            <h1>Kernel Panic</h1>
            <div class="message">' . htmlspecialchars($message) . '</div>
            <div class="info">
                The application has been terminated. Please contact the administrator 
                if this problem persists.
            </div>
        </div>
    </body>
    </html>';
    
    // Terminate script execution
    exit;
}

// Attempt to load configuration
if (!file_exists(BASE_DIR . '/includes/config.php')) {
    kernel_panic('Critical error: Configuration file not found (/includes/config.php)');
}

// Attempt to load autoload
if (!file_exists(BASE_DIR . '/includes/autoload.php')) {
    kernel_panic('Critical error: Configuration file not found (/includes/autoload.php)');
}

// Include configuration file
require_once BASE_DIR . '/includes/config.php';
require_once BASE_DIR . '/includes/autoload.php';

// Verify that essential configuration constants are defined
$required_constants = [
    'SITE_NAME',
    'SITE_URL',
    'SITE_DESCRIPTION',
    'SITE_VERSION',
    'FOOTER_GITHUB',
    'ASSETS_DIR',
    'ENVIRONMENT',
    'ENABLE_CSRF',
    'SESSION_LIFETIME',
    'DEBUG_MODE'
];

$missing_constants = [];
foreach ($required_constants as $constant) {
    if (!defined($constant)) {
        $missing_constants[] = $constant;
    }
}

if (!empty($missing_constants)) {
    kernel_panic('Kernel panic: Configuration missing required constants: ' . implode(', ', $missing_constants));
}

// Set error reporting based on environment
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone if specified in config
if (defined('TIMEZONE') && TIMEZONE) {
    date_default_timezone_set(TIMEZONE);
}

// Additional kernel initialization can be added here
// For example: autoloading, database connection, etc.

// Kernel successfully initialized
define('KERNEL_INITIALIZED', true);
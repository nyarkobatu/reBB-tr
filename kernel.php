<?php
/**
 * reBB - Kernel
 * 
 * This file serves as the application kernel that handles core initialization
 * and configuration loading. It must be included at the beginning of all application
 * entry points.
 */

// Define the base path for the application
if (!defined('APP_VERSION')) {
    define('APP_VERSION', 'v1.5.0');
}

// Define the public path for the application
if (!defined('PUBLIC_DIR')) {
    define('PUBLIC_DIR', dirname(__FILE__));
}

// Define the root directory (project root)
if (!defined('ROOT_DIR')) {
    // If PUBLIC_DIR is pointing to /public/, move up one level
    if (basename(PUBLIC_DIR) === 'public') {
        define('ROOT_DIR', dirname(PUBLIC_DIR));
    } else {
        // Otherwise, assume PUBLIC_DIR is already at the root
        define('ROOT_DIR', PUBLIC_DIR);
    }
}

// Define the public path for the application
if (!defined('STORAGE_DIR')) {
    define('STORAGE_DIR', ROOT_DIR . '/storage');
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
if (!file_exists(ROOT_DIR . '/includes/config.php')) {
    kernel_panic('Critical error: Configuration file not found (/includes/config.php)');
}

// Attempt to load autoload
if (!file_exists(ROOT_DIR . '/core/autorun.php')) {
    kernel_panic('Critical error: Autorun file not found (/core/autorun.php)');
}

// Include configuration file
require_once ROOT_DIR . '/includes/config.php';

// Verify that essential configuration constants are defined
$required_constants = [
    'SITE_NAME',
    'SITE_URL',
    'SITE_DESCRIPTION',
    'APP_VERSION',
    'FOOTER_GITHUB',
    'ASSETS_DIR',
    'ENVIRONMENT',
    'SESSION_LIFETIME',
    'DEBUG_MODE',
    'MAX_REQUESTS_PER_HOUR',
    'COOLDOWN_PERIOD',
    'SESSION_LIFETIME',
    'IP_BLACKLIST',
    'ENABLE_ANALYTICS',
    'TRACK_VISITORS',
    'TRACK_COMPONENTS',
    'TRACK_THEMES',
    'TRACK_FORM_USAGE'
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

// Set default timezone if specified in config
if (defined('TIMEZONE') && TIMEZONE) {
    date_default_timezone_set(TIMEZONE);
}

// Additional kernel initialization can be added here
// For example: autoloading, database connection, etc.

// Load the core autorun file if it exists
$autorun_file = ROOT_DIR . '/core/autorun.php';
if (file_exists($autorun_file)) {
    require_once $autorun_file;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Kernel successfully initialized
define('KERNEL_INITIALIZED', true);
<?php
/**
 * reBB - Core Autorun
 *
 * This file initializes the core system components.
 * It's called by kernel.php to bootstrap the application.
 */

// Always load helpers first
require_once ROOT_DIR . '/core/helpers.php';

// Determine if we're on the setup page - don't load auth if so
$isSetupPage = false;
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $setupPatterns = ['/setup', '/setup/', 'setup.php'];
    
    foreach ($setupPatterns as $pattern) {
        if (strpos($requestUri, $pattern) !== false) {
            $isSetupPage = true;
            break;
        }
    }
}

// Only load auth if not on setup page
if (!$isSetupPage) {
    if (file_exists(ROOT_DIR . '/core/auth.php')) {
        require_once ROOT_DIR . '/core/auth.php';
    }
}

// Load other core files
require_once ROOT_DIR . '/core/routing.php';
require_once ROOT_DIR . '/core/analytics.php';

// Load route definitions
require_once ROOT_DIR . '/routes.php';
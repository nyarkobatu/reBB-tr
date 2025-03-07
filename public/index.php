<?php
/**
 * reBB - Main Entry Point
 *
 * This file serves as the single entry point for the application.
 * It loads the kernel and initializes the routing system.
 */

// Define the base path for the application
// In this case, the base directory will be the public folder
define('PUBLIC_DIR', __DIR__);

// Define the root directory (parent of public)
define('ROOT_DIR', dirname(__DIR__));

// Include the kernel
require_once ROOT_DIR . '/kernel.php';

// Access the router instance that should be created by autorun.php
global $router;

// Process the current request if the router is available
if (isset($router) && $router instanceof Router) {
    $router->processRequest();
} else {
    // If the router is not available, display an error
    echo "Error: Router not initialized. Please check your installation.";
}
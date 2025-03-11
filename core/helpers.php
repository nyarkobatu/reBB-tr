<?php
/**
 * reBB - Helper Functions
 *
 * This file provides helper functions for the application.
 */

/**
 * Get the site URL with optional path
 *
 * @param string $path Optional path to append
 * @return string The complete URL
 */
function site_url($path = '') {
    $base_url = rtrim(SITE_URL, '/');
    return $base_url . '/' . ltrim($path, '/');
}

/**
 * Get the base path for assets with optional path
 *
 * @param string $path Optional path to append
 * @return string The base path for assets
 */
function base_path($path = '') {
    // If ASSETS_DIR is defined as an absolute URL, use it
    if (filter_var(ASSETS_DIR, FILTER_VALIDATE_URL)) {
        return rtrim(ASSETS_DIR, '/') . '/' . ltrim($path, '/');
    }
    
    // Otherwise construct from SITE_URL
    $base_url = rtrim(SITE_URL, '/');
    return $base_url . '/' . ltrim($path, '/');
}

/**
 * Get the base path for assets with optional path
 *
 * @param string $path Optional path to append
 * @return string The base path for assets
 */
function asset_path($path = '') {
    return rtrim(ASSETS_DIR, '/') . '/' . ltrim($path, '/');
}


/**
 * Redirect to a URL
 *
 * @param string $path Path to redirect to
 * @param int $status HTTP status code
 * @return void
 */
function redirect($path, $status = 302) {
    $url = site_url($path);
    header("Location: {$url}", true, $status);
    exit;
}

if (!function_exists('dd')) {
    /**
     * Die and Dump - Dump variables and terminate script execution
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function dd(...$vars) {
        // Set headers to ensure proper content type
        header('Content-Type: text/html; charset=utf-8');
        
        // Start output buffering to capture any prior output
        ob_start();
        
        // Create a styled debug output
        echo '<div style="
            background-color: #f4f4f4; 
            border: 2px solid #ff6f61; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 10px; 
            font-family: monospace; 
            white-space: pre-wrap; 
            word-wrap: break-word; 
            z-index: 9999; 
            position: relative;">';
        
        // Add backtrace information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
        $caller = $backtrace[0];
        
        echo "<strong>Debug Trace:</strong>\n";
        echo "File: {$caller['file']}\n";
        echo "Line: {$caller['line']}\n\n";
        
        // Dump each variable
        foreach ($vars as $var) {
            echo "<strong>Variable Dump:</strong>\n";
            var_dump($var);
            echo "\n";
        }
        
        echo '</div>';
        
        // Output any buffered content
        $output = ob_get_clean();
        
        // Display the debug information
        echo $output;
        
        // Terminate script execution
        die(1);
    }
}

// Optional: Add a companion function for dumping without dying
if (!function_exists('dump')) {
    /**
     * Dump variables without terminating script
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function dump(...$vars) {
        // Similar to dd(), but without dying
        echo '<div style="
            background-color: #f4f4f4; 
            border: 2px solid #61ff6f; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 10px; 
            font-family: monospace; 
            white-space: pre-wrap; 
            word-wrap: break-word; 
            z-index: 9999; 
            position: relative;">';
        
        // Add backtrace information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
        $caller = $backtrace[0];
        
        echo "<strong>Debug Trace:</strong>\n";
        echo "File: {$caller['file']}\n";
        echo "Line: {$caller['line']}\n\n";
        
        // Dump each variable
        foreach ($vars as $var) {
            echo "<strong>Variable Dump:</strong>\n";
            var_dump($var);
            echo "\n";
        }
        
        echo '</div>';
    }
}
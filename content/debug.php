<?php
/**
 * reBB - Debugging Tool
 * 
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to check if a file exists and is readable
function check_file($path, $label = '') {
    echo "<li>";
    if (file_exists($path)) {
        echo "<span style='color:green'>✓</span> $label exists: $path";
        if (is_readable($path)) {
            echo " <span style='color:green'>(readable)</span>";
        } else {
            echo " <span style='color:red'>(not readable - permission issue)</span>";
        }
    } else {
        echo "<span style='color:red'>✗</span> $label doesn't exist: $path";
    }
    echo "</li>";
}

// Function to check directory permissions
function check_directory($path, $label = '') {
    echo "<li>";
    if (is_dir($path)) {
        echo "<span style='color:green'>✓</span> $label directory exists: $path";
        if (is_readable($path)) {
            echo " <span style='color:green'>(readable)</span>";
        } else {
            echo " <span style='color:red'>(not readable - permission issue)</span>";
        }
        if (is_writable($path)) {
            echo " <span style='color:green'>(writable)</span>";
        } else {
            echo " <span style='color:red'>(not writable - permission issue)</span>";
        }
    } else {
        echo "<span style='color:red'>✗</span> $label directory doesn't exist: $path";
    }
    echo "</li>";
}

// Function to check if mod_rewrite is enabled
function check_mod_rewrite() {
    echo "<li>";
    if (function_exists('apache_get_modules')) {
        $modules = apache_get_modules();
        if (in_array('mod_rewrite', $modules)) {
            echo "<span style='color:green'>✓</span> mod_rewrite is enabled";
        } else {
            echo "<span style='color:red'>✗</span> mod_rewrite is NOT enabled. Please enable it in your Apache configuration.";
        }
    } else {
        echo "<span style='color:orange'>?</span> Cannot check if mod_rewrite is enabled (not running as Apache module)";
    }
    echo "</li>";
}

// Fix for the router - simplified version
function fix_router() {
    $router_file = ROOT_DIR . '/core/routing.php';
    if (file_exists($router_file) && is_writable($router_file)) {
        $content = file_get_contents($router_file);
        
        // Fix 1: Ensure getRequestPath properly handles subdirectories
        $fixed_content = str_replace(
            'if ($dir_path != \'/\' && $dir_path != \'\\\\\') {
            $path = substr($path, strlen($dir_path));
        }',
            'if ($dir_path != \'/\' && $dir_path != \'\\\\\' && strpos($path, $dir_path) === 0) {
            $path = substr($path, strlen($dir_path));
        }',
            $content
        );
        
        // Fix 2: Ensure the pattern matching works correctly
        $fixed_content = str_replace(
            '$pattern = \'#^\' . $route[\'pattern\'] . \'$#\';',
            '$pattern = \'#^\' . str_replace(\'/\', \'\\/\', $route[\'pattern\']) . \'$#\';',
            $fixed_content
        );
        
        // Fix 3: Make sure we're checking file existence correctly in content directory
        $fixed_content = str_replace(
            '$view_path = ROOT_DIR . \'/content/\' . $view . \'.php\';',
            '$view_path = ROOT_DIR . \'/content/\' . ltrim($view, \'/\') . \'.php\';',
            $fixed_content
        );
        
        if ($content !== $fixed_content) {
            file_put_contents($router_file, $fixed_content);
            echo "<div style='background-color:#dff0d8;padding:10px;margin:10px 0;border-radius:4px;'>";
            echo "<strong>Router file fixed!</strong> The routing.php file has been updated with fixes.";
            echo "</div>";
        }
    }
}

// Print header
echo "<!DOCTYPE html>
<html>
<head>
    <title>reBB Debugger</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1, h2, h3 { color: #333; }
        ul { list-style-type: none; padding-left: 10px; }
        li { margin-bottom: 5px; }
        .block { background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .error { color: red; }
        .success { color: green; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 4px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>reBB Debugger</h1>";

// Check if config.php exists and try to load it
$config_file = ROOT_DIR . '/includes/config.php';
if (file_exists($config_file)) {
    try {
        include_once $config_file;
        echo "<div class='success'>Successfully loaded config.php</div>";
    } catch (Exception $e) {
        echo "<div class='error'>Error loading config.php: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>config.php not found at: $config_file</div>";
}

// Try to apply fixes
fix_router();

// Server Information
echo "<div class='block'>
    <h2>Server Information</h2>
    <ul>
        <li><strong>PHP Version:</strong> " . phpversion() . "</li>
        <li><strong>Server Software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</li>
        <li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>
        <li><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</li>
        <li><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</li>
        <li><strong>Script Filename:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</li>
    </ul>";

// Check for mod_rewrite
echo "<h3>Apache Configuration</h3>
    <ul>";
check_mod_rewrite();
echo "</ul></div>";

// Debug Routing
echo "<div class='block'>
    <h2>Session Information</h2>";

    $auth = auth()->getUser();

echo "<ul>
    <li><strong>Session save path:</strong> ". session_save_path() ."</li>
    <li><strong>Session cookie parameters:</strong> ". print_r(session_get_cookie_params()) ."</li>
    <li><strong>Session ID:</strong> ". session_id() ."</li>
    <li><strong>Session Token:</strong> ". $_SESSION['auth_token'] ."</li>
    <li><strong>CSRF Token:</strong> ". $_SESSION['csrf_token'] ."</li>
    <li><strong>Auth Information:</strong> ". $auth ."</li>
</ul>";

echo "</div>";

// Directory Structure
echo "<div class='block'>
    <h2>Directory Structure Check</h2>
    <ul>";
check_directory(ROOT_DIR, 'Root');
check_directory(PUBLIC_DIR, 'Public');
check_directory(ROOT_DIR . '/core', 'Core');
check_directory(ROOT_DIR . '/content', 'Content');
check_directory(ROOT_DIR . '/includes', 'Includes');
echo "</ul></div>";

// Critical Files Check
echo "<div class='block'>
    <h2>Critical Files Check</h2>
    <ul>";
check_file(ROOT_DIR . '/.htaccess', 'Root .htaccess');
check_file(PUBLIC_DIR . '/.htaccess', 'Public .htaccess');
check_file(PUBLIC_DIR . '/index.php', 'Main entry point');
check_file(ROOT_DIR . '/kernel.php', 'Kernel');
check_file(ROOT_DIR . '/core/autorun.php', 'Autorun');
check_file(ROOT_DIR . '/core/routing.php', 'Router');
check_file(ROOT_DIR . '/core/helpers.php', 'Helpers');
check_file(ROOT_DIR . '/routes.php', 'Routes');
check_file(ROOT_DIR . '/content/front-page.php', 'Home view');
check_file(ROOT_DIR . '/content/errors/404.php', '404 error view');
echo "</ul></div>";

// File Content Check
echo "<div class='block'>
    <h2>File Content Check</h2>";

// Check public/.htaccess
$htaccess_path = PUBLIC_DIR . '/.htaccess';
if (file_exists($htaccess_path) && is_readable($htaccess_path)) {
    echo "<h3>public/.htaccess content:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccess_path)) . "</pre>";
    
    // Check for common issues
    $htaccess_content = file_get_contents($htaccess_path);
    if (strpos($htaccess_content, 'RewriteEngine On') === false) {
        echo "<div class='error'>RewriteEngine On is missing from .htaccess</div>";
    }
    if (strpos($htaccess_content, 'RewriteRule ^ index.php [L]') === false) {
        echo "<div class='error'>The rule to route all requests to index.php is missing or incorrect</div>";
    }
}

// Check routes.php
$routes_path = ROOT_DIR . '/routes.php';
if (file_exists($routes_path) && is_readable($routes_path)) {
    echo "<h3>routes.php content:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($routes_path)) . "</pre>";
}
echo "</div>";

// Debug Routing
echo "<div class='block'>
    <h2>Debug Current Request</h2>";

// Parse the request URI
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = parse_url($request_uri);
$path = $uri_parts['path'] ?? '/';
$query = $uri_parts['query'] ?? '';

// Get script details
$script_name = $_SERVER['SCRIPT_NAME'];
$dir_path = dirname($script_name);

echo "<ul>
    <li><strong>Requested Path:</strong> $path</li>
    <li><strong>Query String:</strong> $query</li>
    <li><strong>Script Directory:</strong> $dir_path</li>
</ul>";

// If the script is in a subdirectory, show how the path would be adjusted
if ($dir_path != '/' && $dir_path != '\\') {
    if (strpos($path, $dir_path) === 0) {
        $adjusted_path = substr($path, strlen($dir_path));
        $adjusted_path = '/' . ltrim($adjusted_path, '/');
        echo "<p>Path after subdirectory adjustment: <strong>$adjusted_path</strong></p>";
    } else {
        echo "<p class='error'>Path does not start with the script directory, which might cause routing issues.</p>";
    }
}
echo "</div>";

// Test link generator to help diagnose URL issues
echo "<div class='block'>
    <h2>Test Links</h2>
    <p>Click these links to test if they correctly route to the right pages:</p>
    <ul>
        <li><a href='/' target='_blank'>Home Page (/)</a></li>
        <li><a href='/form' target='_blank'>Form Page (/form)</a></li>
        <li><a href='/builder' target='_blank'>Builder Page (/builder)</a></li>
        <li><a href='/docs' target='_blank'>Documentation Page (/docs)</a></li>
        <li><a href='/non-existent-page' target='_blank'>Non-existent Page (should show 404)</a></li>
    </ul>
</div>";

// Provide suggestions to fix common issues
echo "<div class='block'>
    <h2>Common Solutions</h2>
    <ol>
        <li><strong>Fix .htaccess:</strong> Make sure mod_rewrite is enabled and .htaccess files are being processed (AllowOverride All in Apache config).</li>
        <li><strong>Check Permissions:</strong> Ensure all directories and files have proper read permissions.</li>
        <li><strong>Subdirectory Fix:</strong> If running in a subdirectory, make sure your base URL in config.php is correct.</li>
        <li><strong>Router Fix:</strong> The routing code has been automatically fixed to better handle subdirectories.</li>
        <li><strong>Check Error Logs:</strong> Review your server error logs for more specific error messages.</li>
    </ol>
</div>";

// Footer
echo "<hr><p><em>Generated at: " . date('Y-m-d H:i:s') . "</em></p></body></html>";
<?php
/**
 * reBB - Routing System
 *
 * This file provides the routing functionality for the application.
 */

class Router {
    /**
     * @var array Registered routes
     */
    private $routes = [];
    
    /**
     * @var array Legacy route mappings
     */
    private $legacy_routes = [];
    
    /**
     * Register a route
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $pattern URL pattern
     * @param callable $callback Function to call
     * @return void
     */
    public function register($method, $pattern, $callback) {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }
    
    /**
     * Register a GET route
     *
     * @param string $pattern URL pattern
     * @param callable $callback Function to call
     * @return void
     */
    public function get($pattern, $callback) {
        $this->register('GET', $pattern, $callback);
    }
    
    /**
     * Register a POST route
     *
     * @param string $pattern URL pattern
     * @param callable $callback Function to call
     * @return void
     */
    public function post($pattern, $callback) {
        $this->register('POST', $pattern, $callback);
    }
    
    /**
     * Register any HTTP method route
     *
     * @param string $pattern URL pattern
     * @param callable $callback Function to call
     * @return void
     */
    public function any($pattern, $callback) {
        $this->register('ANY', $pattern, $callback);
    }
    
    /**
     * Register a legacy route mapping
     *
     * @param string $legacy_pattern The legacy URL pattern
     * @param string $new_pattern The new URL pattern
     * @return void
     */
    public function legacy($legacy_pattern, $new_pattern) {
        $this->legacy_routes[$legacy_pattern] = $new_pattern;
    }
    
    /**
     * Get the current request path
     *
     * @return string The request path
     */
    private function getRequestPath() {
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Parse the URI
        $uri_parts = parse_url($request_uri);
        $path = $uri_parts['path'] ?? '/';
        
        // Get the base path/installation directory
        $script_name = $_SERVER['SCRIPT_NAME'];
        $dir_path = rtrim(dirname($script_name), '/\\');
        
        // If we're in a subdirectory and the path starts with that directory, remove it
        if ($dir_path !== '' && $dir_path !== '/' && strpos($path, $dir_path) === 0) {
            $path = substr($path, strlen($dir_path));
        }
        
        // Ensure path starts with /
        if (empty($path)) {
            $path = '/';
        } else {
            $path = '/' . ltrim($path, '/');
        }
        
        // Normalize path by removing trailing slashes (except for root "/")
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        
        return $path;
    }
    /**
     * Check for legacy URLs and redirect if needed
     *
     * @return bool True if redirected, false otherwise
     */
    private function handleLegacyRedirect() {
        // Get the current path and query string
        $path = $this->getRequestPath();
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        
        // Handle direct PHP file access (legacy URLs)
        foreach ($this->legacy_routes as $legacy_pattern => $new_pattern) {
            // Check if the current path matches a legacy pattern
            if ($path === '/' . $legacy_pattern) {
                // Build the redirect URL
                $redirect_to = site_url($new_pattern);
                if (!empty($query_string)) {
                    $redirect_to .= '?' . $query_string;
                }
                
                // Redirect to the new URL
                header('Location: ' . $redirect_to, true, 301);
                exit;
            }
        }
        
        return false;
    }
    
    /**
     * Process the current request
     *
     * @return void
     */
    public function processRequest() {
        // Get the request method and path
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getRequestPath();
        
        // Debug info - helpful for troubleshooting
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Routing - Method: $method, Path: $path");
        }
        
        // Check for legacy URLs and redirect if needed
        $this->handleLegacyRedirect();
        
        // Try to match against registered routes
        foreach ($this->routes as $route) {
            // Skip if method doesn't match
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }
            
            // Prepare pattern for regex matching
            $pattern = $route['pattern'];
            
            // Convert route pattern to regex
            $regex_pattern = preg_replace('/\/:([^\/]+)/', '/([^/]+)', $pattern);
            $regex_pattern = '#^' . $regex_pattern . '$#';
            
            // Try to match the path against the pattern
            if (preg_match($regex_pattern, $path, $matches)) {
                // If we have named parameters, extract them
                $params = [];
                
                // Use preg_match with named capture groups to get the parameters
                if (preg_match_all('/:([^\/]+)/', $pattern, $param_names)) {
                    // Remove the first element (full match)
                    array_shift($matches);
                    
                    // Map parameter names to values
                    foreach ($param_names[1] as $index => $name) {
                        if (isset($matches[$index])) {
                            $params[$name] = $matches[$index];
                        }
                    }
                }
                
                // Call the route callback with parameters
                call_user_func($route['callback'], $params);
                return;
            }
        }
        
        // If no route matches, show a 404 page
        http_response_code(404);
        view('errors/404');
    }
}

// Create a global router instance
$router = new Router();

// Register helper functions for routes
function get($pattern, $callback) {
    global $router;
    $router->get($pattern, $callback);
}

function post($pattern, $callback) {
    global $router;
    $router->post($pattern, $callback);
}

function any($pattern, $callback) {
    global $router;
    $router->any($pattern, $callback);
}

function legacy($legacy_pattern, $new_pattern) {
    global $router;
    $router->legacy($legacy_pattern, $new_pattern);
}

/**
 * Render a view
 *
 * @param string $view The view name
 * @param array $data Optional data to pass to the view
 * @return void
 */
function view($view, $data = []) {
    // Extract data variables so they're available in the view
    if (!empty($data)) {
        extract($data);
    }
    
    // Include the view file
    $view_path = ROOT_DIR . '/content/' . ltrim($view, '/') . '.php';
    
    if (file_exists($view_path)) {
        include $view_path;
    } else {
        // If view doesn't exist, show an error
        kernel_panic("View not found: " . htmlspecialchars($view));
    }
}
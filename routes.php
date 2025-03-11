<?php
/**
 * reBB - Route Definitions
 *
 * This file defines all routes for the application in a logical, grouped manner.
 */

// ===================================
// Legacy URL Support
// ===================================
// Map old file-based URLs to the new route structure
legacy('index.php', '');
legacy('form.php', 'form');
legacy('builder.php', 'builder');
legacy('documentation.php', 'docs');


// ===================================
// Authentication Routes
// ===================================
// Login page
any('/login', function() {
    view('auth/login');
});

// Logout action
any('/logout', function() {
    auth()->logout();
    redirect('');
});

// ===================================
// Public-facing Routes
// ===================================
// Home page
get('/', function() {
    view('front-page');
});

// ===================================
// Form Routes - For viewing and using forms
// ===================================
// Form listing page
get('/form', function() {
    view('form');
});

// View specific form by ID
get('/form/:id', function($params) {
    $_GET['f'] = $params['id']; // Store ID in GET param for backward compatibility
    view('form');
});

// ===================================
// Builder Routes - For creating and editing forms
// ===================================
// Form builder - create new form
get('/builder', function() {
    view('builder');
});

// Form builder - edit existing form
get('/builder/:id', function($params) {
    $_GET['f'] = $params['id']; // Store ID in GET param for backward compatibility
    view('builder');
});

// ===================================
// API Routes
// ===================================
// AJAX endpoint for form operations
any('/ajax', function() {
    view('ajax');
});

// ===================================
// Admin & Management Routes
// ===================================
// Admin panel
any('/admin', function() {
    view('admin/admin');
});

// Analytics dashboard
any('/analytics', function() {
    view('admin/analytics');
});

// ===================================
// Documentation Routes
// ===================================
// Documentation home
any('/docs', function() {
    view('documentation');
});

// Specific documentation page
get('/docs/:id', function($params) {
    $_GET['doc'] = $params['id']; // Store ID in GET param for backward compatibility
    view('documentation');
});

// ===================================
// Development Routes
// ===================================
// Debug page - only available in development mode
if(DEBUG_MODE === true) {
    get('/debug', function() {
        view('debug');
    });

    get('/test', function() {
        echo 'Hello World!';
    });
}

// Setup page - only accessible if no users exist
any('/setup', function() {
    // Check if users exist without requiring Auth class
    function checkUsersExist() {
        $dbPath = ROOT_DIR . '/db/users';
        if (!is_dir($dbPath)) {
            return false;
        } else return true;
    }
    
    // Redirect if users already exist
    if (checkUsersExist()) {
        header('Location: ' . site_url());
        exit;
    }
    
    view('setup');
});
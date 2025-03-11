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
legacy('edit.php', 'edit');


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
// Edit Form Routes - For authenticated editing of forms
// ===================================
// Edit form - authenticated edit of existing form with ownership check
// Change this route from a path parameter to a query parameter
get('/edit', function() {
    // This now handles /edit?f=formId format
    if (!isset($_GET['f']) || empty($_GET['f'])) {
        // No form ID provided
        http_response_code(400);
        echo '<div class="alert alert-danger">No form ID provided. Please specify a form to edit.</div>';
        return;
    }
    
    // Require authentication
    auth()->requireAuth('login');
    
    // Check if the form exists and the user has permission to edit it
    $formId = $_GET['f'];
    $filename = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
    
    if (file_exists($filename)) {
        $formData = json_decode(file_get_contents($filename), true);
        $currentUser = auth()->getUser();
        
        // Check if form has a createdBy field and if it matches the current user
        // Admin users can edit any form
        if (isset($formData['createdBy']) && $formData['createdBy'] === $currentUser['_id'] || 
            $currentUser['role'] === 'admin') {
            // User has permission to edit, redirect to builder with edit mode
            $_GET['edit_mode'] = 'true';
            view('builder');
        } else {
            // User doesn't have permission to edit this form
            http_response_code(403);
            echo '<div class="alert alert-danger">You do not have permission to edit this form.</div>';
        }
    } else {
        // Form not found
        http_response_code(404);
        view('errors/404');
    }
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

// User management
any('/admin/users', function() {
    view('admin/users');
});

// Account sharing
any('/admin/share', function() {
    view('admin/share');
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
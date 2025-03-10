<?php
/**
 * reBB - Route Definitions
 *
 * This file defines all the routes for the application.
 */

// Define legacy route mappings
legacy('index.php', '');
legacy('form.php', 'form');
legacy('builder.php', 'builder');
legacy('documentation.php', 'docs');

// Home page
get('/', function() {
    view('front-page');
});

// Form page
get('/form', function() {
    view('form');
});

// Form with ID
get('/form/:id', function($params) {
    $_GET['f'] = $params['id'];
    view('form');
});

// Builder page
get('/builder', function() {
    view('builder');
});

// Builder with ID
get('/builder/:id', function($params) {
    $_GET['f'] = $params['id'];
    view('builder');
});

any('/ajax', function() {
    view('ajax');
});

// Admin page
any('/admin', function() {
    view('admin');
});

// Documentation page
any('/docs', function() {
    view('documentation');
});

// Documentation with ID
get('/docs/:id', function($params) {
    $_GET['doc'] = $params['id'];
    view('documentation');
});

if(DEBUG_MODE === true) {
    get('/debug', function() {
        view('debug');
    });
}

// Analytics dashboard route
any('/analytics', function() {
    view('analytics');
});
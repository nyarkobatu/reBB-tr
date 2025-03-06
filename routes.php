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
    view('index');
});

// Form page
get('/form', function() {
    view('form');
});

// Form with ID
get('/form/:id', function($params) {
    $_GET['f'] = $params['id']; // Set the form ID in $_GET for backward compatibility
    view('form');
});

// Builder page
get('/builder', function() {
    view('builder');
});

// Builder with ID
get('/builder/:id', function($params) {
    $_GET['f'] = $params['id']; // Set the form ID in $_GET for backward compatibility
    view('builder');
});

// Ajax endpoint - handle both GET and POST
any('/ajax', function() {
    view('ajax');
});

// Admin page
get('/admin', function() {
    view('admin');
});
post('/admin', function() {
    view('admin');
});

// Documentation page
get('/docs', function() {
    view('documentation');
});
post('/docs', function() {
    view('documentation');
});

// Documentation with ID
get('/docs/:id', function($params) {
    $_GET['doc'] = $params['id']; // Set the doc ID in $_GET for backward compatibility
    view('documentation');
});
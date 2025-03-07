<?php
/**
 * reBB - Homepage
 * 
 * This file serves as the entry point for the application.
 */

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container home-container">
    <div class="form-box">
        <h2 class="text-center mb-4"><?php echo SITE_NAME; ?></h2>
        <button id="createFormBtn" class="btn btn-primary btn-block">Create a form</button>
        <button id="useFormBtn" class="btn btn-secondary btn-block">Use a form</button>

        <div id="hashInputContainer">
            <div class="form-group">
                <label for="shareableHash">Form:</label>
                <input type="text" class="form-control" id="shareableHash" placeholder="Enter the hash/id for the form you wish to use">
            </div>
            <button id="submitHashBtn" class="btn btn-success btn-block">Submit</button>
        </div>

        <div class="mt-3">
            <a href="<?php echo site_url('docs'); ?>" class="btn btn-info btn-block">
                <i class="bi bi-book"></i> Documentation
            </a>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/front-page.css') .'?v=' . APP_VERSION . '">';

// Add page-specific JavaScript
$site_url = site_url();
$GLOBALS['page_js_vars'] = <<<JSVARS
var current_header = "$site_url";
JSVARS;
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/app/index.js') .'?v=' . APP_VERSION . '"></script>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';
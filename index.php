<?php
/**
 * reBB - Homepage
 * 
 * This file serves as the entry point for the application.
 */
require_once 'site.php';

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container">
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
            <a href="<?php echo SITE_URL; ?>/documentation.php" class="btn btn-info btn-block">
                <i class="bi bi-book"></i> Documentation
            </a>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Add page-specific JavaScript
$GLOBALS['page_javascript'] = '<script src="'. ASSETS_DIR .'/js/app/index.js?v=' . SITE_VERSION . '"></script>';

// Include the master layout
require_once BASE_DIR . '/includes/master.php';
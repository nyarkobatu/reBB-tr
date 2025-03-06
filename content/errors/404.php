<?php
/**
 * reBB - 404 Error Page
 * 
 * This file displays a 404 error when a page is not found.
 */

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container">
    <div class="form-box">
        <h2 class="text-center mb-4">Page Not Found</h2>
        <div class="alert alert-danger">
            The page you requested could not be found.
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo site_url(); ?>" class="btn btn-primary">Go to Homepage</a>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = '404 Not Found';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';
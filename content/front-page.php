<?php
/**
 * reBB - Homepage
 * 
 * This file serves as the entry point for the application.
 */

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="landing-page">
    <!-- Animated gradient background -->
    <div class="gradient-background"></div>
    
    <div class="landing-container">
        <!-- Version badge -->
        <div class="version-badge"><?php echo APP_VERSION; ?></div>
        
        <!-- Left side with description -->
        <div class="landing-info">
            <h1><?php echo SITE_NAME; ?></h1>
            <p class="tagline"><?php echo SITE_DESCRIPTION; ?></p>
            
            <div class="description">
                <p>Create custom forms without any coding knowledge. Generate structured HTML/BBCode content with a simple drag-and-drop interface.</p>
                <ul>
                    <li><i class="bi bi-check-circle"></i> Easy form creation</li>
                    <li><i class="bi bi-check-circle"></i> Shareable unique URLs</li>
                    <li><i class="bi bi-check-circle"></i> No login required</li>
                    <li><i class="bi bi-check-circle"></i> Minimalistic form styles</li>
                </ul>
            </div>
            
            <div class="docs-link">
                <a href="<?php echo site_url('docs'); ?>">
                    <i class="bi bi-book"></i> Learn more in our documentation
                </a>
            </div>
        </div>
        
        <!-- Right side with form actions -->
        <div class="landing-actions">
            <div class="actions-card">
                <h2>Get Started</h2>
                
                <button id="createFormBtn" class="btn btn-primary btn-action">
                    <i class="bi bi-plus-circle"></i> Create a form
                </button>
                
                <button id="useFormBtn" class="btn btn-secondary btn-action">
                    <i class="bi bi-box-arrow-in-right"></i> Use a form
                </button>
                
                <div id="hashInputContainer" class="hash-input-container">
                    <div class="form-group">
                        <label for="shareableHash">Form ID or URL:</label>
                        <input type="text" class="form-control" id="shareableHash" placeholder="Enter the form ID">
                    </div>
                    <button id="submitHashBtn" class="btn btn-success btn-action">
                        <i class="bi bi-arrow-right-circle"></i> Continue
                    </button>
                </div>
                
                <div class="additional-links">
                    <a href="<?php echo site_url('docs'); ?>" class="text-decoration-none">
                        <i class="bi bi-question-circle"></i> How it works
                    </a>
                    <a href="<?php echo FOOTER_GITHUB; ?>" target="_blank" class="text-decoration-none">
                        <i class="bi bi-github"></i> GitHub
                    </a>
                    <a href="<?php echo site_url('donate'); ?>" class="text-decoration-none donate-link">
                        <i class="bi bi-heart"></i> Donate
                    </a>
                    <a href="#" class="dark-mode-toggle text-decoration-none">
                        <i class="bi bi-moon"></i> Dark Mode
                    </a>
                </div>

                <!-- Login button -->
                <div class="login-button">
                    <?php if (!auth()->isLoggedIn()): ?>
                    <a href="<?php echo site_url('login'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person"></i> Login
                    </a>
                    <?php else: ?>
                    <a href="<?php echo site_url('profile'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person"></i> <?php echo htmlspecialchars(auth()->getUser()['username']); ?>
                    </a>
                    <?php endif ?>
                </div>
            </div>
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
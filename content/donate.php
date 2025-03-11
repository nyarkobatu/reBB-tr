<?php
/**
 * reBB - Donation Page
 * 
 * This file serves as the page for users to support the project through donations.
 */

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="donate-page">
    <!-- Animated background effect -->
    <div class="donate-background">
        <div class="donate-particles"></div>
    </div>

    <div class="donate-container">
        <div class="donate-header">
            <h1>Support <span class="highlight"><?php echo SITE_NAME; ?></span></h1>
            <p class="tagline">Help keep this free tool available for everyone</p>
        </div>

        <div class="donate-content">
            <div class="donate-section">
                <h2><i class="bi bi-heart-fill pulse"></i> Why Donate?</h2>
                <p>I created reBB with a simple goal: to provide a free, open-source tool that makes creating formatted content easy for everyone. This project has been a labor of love, and it will <strong>always remain free and open-source</strong>.</p>
                
                <p>For the longest time, I've been covering the server costs myself. While it's not a huge expense (about €5 per month), any contribution would mean a lot and help ensure the service stays online and continues to improve.</p>
            </div>

            <div class="donate-section features-section">
                <h2><i class="bi bi-gift"></i> What You'll Unlock</h2>
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-person-circle"></i></div>
                        <h3>Personal Account</h3>
                        <p>Get your own user account with an overview of all forms you've created.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-pencil-square"></i></div>
                        <h3>Edit Your Forms</h3>
                        <p>Make changes to your previously created forms at any time.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-graph-up"></i></div>
                        <h3>Form Statistics</h3>
                        <p>See how many people accessed and used your forms with detailed analytics.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-floppy-fill"></i></div>
                        <h3>Increased Form Size Limit</h3>
                        <p>Increased from 1MB to 10MB per form.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-emoji-smile"></i></div>
                        <h3>Eternal Gratitude</h3>
                        <p>My sincere appreciation for supporting this project and keeping it alive!</p>
                    </div>
                </div>
                <p class="features-note">And more features may be added in the future!</p>
            </div>

            <div class="donate-section note-section">
                <div class="note-box">
                    <h3><i class="bi bi-info-circle"></i> Note on Paywalling</h3>
                    <p>I'm not here to paywall features for any intentional purposes. Anyone is fully able to host their own version of this website without any sort of paywall - that's the beauty of open-source!</p>
                    <p>Donations simply help cover the costs of keeping the public instance running and provide some nice perks as a thank you.</p>
                </div>
            </div>

            <div class="donate-action">
                <h2>Ready to Support?</h2>
                <p>Your contribution, no matter the size, makes a difference!</p>
                <a href="https://ko-fi.com/booskit" target="_blank" class="ko-fi-button">
                    <i class="bi bi-cup-hot"></i> Support on Ko-fi
                </a>
                <p>Donate any amount, get full access.</p>
                <div class="thank-you-message">
                    Thank you for considering a donation and for using reBB!
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Support reBB';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/donate.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';
?>
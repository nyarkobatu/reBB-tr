<?php
/**
 * Master layout template
 * 
 * This file serves as the main layout template for all pages.
 * It includes the common header and footer elements.
 */

 // Function to get settings with fallback to default values
function page_setting($key, $default = null) {
    if (isset($GLOBALS['page_settings'][$key])) {
        return $GLOBALS['page_settings'][$key];
    }
    return null;
}

// Function to include the content from the calling page
function yield_content() {
    if (isset($GLOBALS['page_content'])) {
        echo $GLOBALS['page_content'];
    }
}

// Function to include custom CSS from the calling page
function yield_css() {
    if (isset($GLOBALS['page_css'])) {
        echo $GLOBALS['page_css'];
    }
}

// Function to include custom JavaScript from the calling page
function yield_javascript() {
    if (isset($GLOBALS['page_javascript'])) {
        echo $GLOBALS['page_javascript'];
    }
}

function yield_js_vars() {
    if (isset($GLOBALS['page_js_vars'])) {
        echo $GLOBALS['page_js_vars'];
    }
}

// Function to set page title (with default fallback)
function get_page_title() {
    if (isset($GLOBALS['page_title'])) {
        return $GLOBALS['page_title'] . " - " . SITE_NAME;
    }
    return SITE_NAME . " - " . SITE_DESCRIPTION;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_page_title(); ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo site_url(); ?>/resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo site_url(); ?>/resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo site_url(); ?>/resources/favicon-16x16.png">
    <link rel="manifest" href="<?php echo site_url(); ?>/resources/site.webmanifest">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo asset_path('css/app.css'); ?>?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="<?php echo asset_path('css/dark-mode.css'); ?>?v=<?php echo APP_VERSION; ?>">
    <?php if (page_setting('formio_assets', true)): ?>
        <link rel="stylesheet" href="https://cdn.form.io/js/formio.full.min.css">
        <script src='https://cdn.form.io/js/formio.full.min.js'></script>
    <?php endif; ?>
    <?php yield_css(); ?>
</head>
<body>
    <?php yield_content(); ?>

    <?php if (page_setting('footer', 'form')): ?>
        <footer class="footer">
            <p>Made using <a href="<?php echo site_url(); ?>" target="_blank"><?php echo SITE_NAME; ?></a> <?php echo APP_VERSION; ?></br>
            <?php if (isset($_GET['f']) && !empty($_GET['f'])): ?>
                <a href="?f=<?php echo htmlspecialchars($_GET['f']) ?>/json">View form in json</a> • 
                <a href="<?php echo site_url('builder'); ?>?f=<?php echo htmlspecialchars($_GET['f']) ?>">Use this form as a template</a> • 
                <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a><br/>
            <?php else: ?>
                <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a><br/>
            <?php endif; ?>
            <a href="<?php echo FOOTER_GITHUB; ?>">Github</a></p>
        </footer>
    <?php else: ?>
        <footer class="footer">
            <p>Made with ❤️ by <a href="https://booskit.dev/" target="_blank">booskit</a></br>
            <a href="<?php echo FOOTER_GITHUB; ?>" target="_blank">Github</a> • 
            <a href="<?php echo site_url('docs'); ?>" target="_blank">Documentation</a> • 
            <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a></br>
            <span style="font-size: 12px;"><?php echo APP_VERSION; ?></span></p>
        </footer>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script><?php yield_js_vars(); ?></script>
    <script src="<?php echo asset_path('js/common.js'); ?>?v=<?php echo APP_VERSION; ?>"></script>
    <?php yield_javascript(); ?>
</body>
</html>
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
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL; ?>/resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL; ?>/resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL; ?>/resources/favicon-16x16.png">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/resources/site.webmanifest">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_DIR; ?>/css/app.css?v=<?php echo SITE_VERSION; ?>">
    <link rel="stylesheet" href="<?php echo ASSETS_DIR; ?>/css/dark-mode.css?v=<?php echo SITE_VERSION; ?>">
    <?php if (page_setting('formio_assets', true)): ?>
    <link rel="stylesheet" href="https://cdn.form.io/js/formio.full.min.css">
    <script src='https://cdn.form.io/js/formio.full.min.js'></script>
    <?php endif; ?>
    <?php yield_css(); ?>
</head>
<body>
    <?php yield_content(); ?>

    <footer class="footer">
        <p>Made with ❤️ by <a href="https://booskit.dev/" target="_blank">booskit</a></br>
        <a href="<?php echo FOOTER_GITHUB; ?>" target="_blank">Github</a> • 
        <a href="<?php echo SITE_URL; ?>/documentation.php" target="_blank">Documentation</a> • 
        <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a></br>
        <span style="font-size: 12px;"><?php echo SITE_VERSION; ?></span></p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script><?php yield_js_vars(); ?></script>
    <script src="<?php echo ASSETS_DIR; ?>/js/common.js?v=<?php echo SITE_VERSION; ?>"></script>
    <?php yield_javascript(); ?>
</body>
</html>
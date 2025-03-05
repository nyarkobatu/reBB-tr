<?php
/**
 * reBB - Configuration File
 * 
 * This file contains all the core configuration settings for the reBB application.
 * It defines constants that are used throughout the application to maintain
 * consistency and allow for easy updates.
 * 
 * @package reBB
 * @author booskit-codes
 */

// ╔════════════════════════════════════════╗
// ║            SITE CONFIGURATION          ║
// ╚════════════════════════════════════════╝

// Site identity
define('SITE_NAME',        'reBB');
define('SITE_DESCRIPTION', 'BBCode done differently');

// URLs and paths
define('SITE_URL',         'http://localhost/reBB');
define('FOOTER_GITHUB',    'https://github.com/booskit-codes/reBB');

// Directory structure
define('ASSETS_DIR',       SITE_URL . '/assets');

// ╔════════════════════════════════════════╗
// ║       APPLICATION CONFIGURATION        ║
// ╚════════════════════════════════════════╝

// Environment: 'development' or 'production'
define('ENVIRONMENT',      'production');

// Security settings
define('ENABLE_CSRF',      true);
define('SESSION_LIFETIME', 86400); // 24 hours in seconds

// Form Builder Submission settings


// Feature flags
define('DEBUG_MODE',         ENVIRONMENT === 'development');

/**
 * End of configuration
 */
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
define('SITE_URL',         'https://rebb.booskit.dev');
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
define('SESSION_LIFETIME', 86400);      // 24 hours in seconds

// Form Builder Submission settings
define('MAX_REQUESTS_PER_HOUR', 60);    // Maximum form submissions per hour per IP
define('COOLDOWN_PERIOD', 5);           // Seconds between submissions
define('IP_BLACKLIST', ['192.0.2.1']);  // Example blacklisted IPs (replace with actual ones)

// Feature flags
define('DEBUG_MODE',         ENVIRONMENT === 'development');

// ╔════════════════════════════════════════╗
// ║         ANALYTICS CONFIGURATION        ║
// ╚════════════════════════════════════════╝

// Enable or disable the analytics system globally
define('ENABLE_ANALYTICS',    true);

// Configure what to track
define('TRACK_VISITORS',      true);    // Track page views and visitor counts
define('TRACK_COMPONENTS',    true);    // Track component usage statistics
define('TRACK_THEMES',        true);    // Track theme selection statistics
define('TRACK_FORM_USAGE',    true);    // Track form views and submissions

/**
 * End of configuration
 */
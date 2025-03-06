<?php
/**
 * reBB - Helper Functions
 *
 * This file provides helper functions for the application.
 */

/**
 * Get the site URL with optional path
 *
 * @param string $path Optional path to append
 * @return string The complete URL
 */
function site_url($path = '') {
    $base_url = rtrim(SITE_URL, '/');
    return $base_url . '/' . ltrim($path, '/');
}

/**
 * Get the base path for assets with optional path
 *
 * @param string $path Optional path to append
 * @return string The base path for assets
 */
function base_path($path = '') {
    // If ASSETS_DIR is defined as an absolute URL, use it
    if (filter_var(ASSETS_DIR, FILTER_VALIDATE_URL)) {
        return rtrim(ASSETS_DIR, '/') . '/' . ltrim($path, '/');
    }
    
    // Otherwise construct from SITE_URL
    $base_url = rtrim(SITE_URL, '/');
    return $base_url . '/' . ltrim($path, '/');
}

/**
 * Get the base path for assets with optional path
 *
 * @param string $path Optional path to append
 * @return string The base path for assets
 */
function asset_path($path = '') {
    return rtrim(ASSETS_DIR, '/') . '/' . ltrim($path, '/');
}


/**
 * Redirect to a URL
 *
 * @param string $path Path to redirect to
 * @param int $status HTTP status code
 * @return void
 */
function redirect($path, $status = 302) {
    $url = site_url($path);
    header("Location: {$url}", true, $status);
    exit;
}
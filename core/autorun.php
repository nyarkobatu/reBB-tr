<?php
/**
 * reBB - Core Autorun
 *
 * This file initializes the core system components.
 * It's called by kernel.php to bootstrap the application.
 */

// Load core files
require_once ROOT_DIR . '/core/helpers.php';
require_once ROOT_DIR . '/core/routing.php';

// Load route definitions
require_once ROOT_DIR . '/routes.php';
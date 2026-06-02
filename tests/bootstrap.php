<?php
/**
 * Minimal test bootstrap for the uzelok project.
 * Loads Composer autoloader and sets up error reporting.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Ensure we are using the project's helpers
require_once dirname(__DIR__) . '/core/helpers.php';
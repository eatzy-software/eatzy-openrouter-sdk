<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap File
|--------------------------------------------------------------------------
|
| This file is included before running any tests. You can use it to set
| up any environment variables, constants, or perform any setup tasks
| needed for your test suite.
|
*/

// Ensure we're using the test environment
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Set timezone for consistent testing
date_default_timezone_set('UTC');

// Increase memory limit for tests
ini_set('memory_limit', '512M');

// Enable error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoload Composer dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Define test constants
if (!defined('TESTS_PATH')) {
    define('TESTS_PATH', __DIR__);
}

if (!defined('FIXTURES_PATH')) {
    define('FIXTURES_PATH', __DIR__ . '/Fixtures');
}

// Create fixtures directory if it doesn't exist
if (!is_dir(FIXTURES_PATH)) {
    mkdir(FIXTURES_PATH, 0755, true);
}

<?php
/*
 * PHPUnit Bootstrap File
 * Setup test environment and autoloading
 */

// Set environment to testing
putenv('APP_ENV=testing');

// Composer autoloader
require __DIR__ . '/../../vendor/autoload.php';

// Load Laravel environment
if (file_exists(__DIR__ . '/../../.env.testing')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../..', '.env.testing');
    $dotenv->load();
}

// Register test helpers
require __DIR__ . '/Helpers/TestHelper.php';

<?php

use Wp\FastEndpoints\Depends\Tests\Helpers\Helpers;
use Yoast\WPTestUtils\WPIntegration;

/**
 * Include core bootstrap for an integration test suite
 *
 * This will only work if you run the tests from the command line.
 * Running the tests from IDE such as PhpStorm will require you to
 * add additional argument to the test run command if you want to run
 * integration tests.
 */
if (! Helpers::isIntegrationTest()) {
    return;
}

if (! file_exists(dirname(__FILE__, 2).'/wp/tests/phpunit/wp-tests-config.php')) {
    // We need to set up core config details and test details
    copy(
        dirname(__FILE__, 2).'/wp/wp-tests-config-sample.php',
        dirname(__FILE__, 2).'/wp/tests/phpunit/wp-tests-config.php'
    );

    // Change certain constants from the test's config file.
    $testConfigPath = dirname(__FILE__, 2).'/wp/tests/phpunit/wp-tests-config.php';
    $testConfigContents = file_get_contents($testConfigPath);

    $testConfigContents = str_replace(
        "dirname( __FILE__ ) . '/src/'",
        "dirname(__FILE__, 3) . '/src/'",
        $testConfigContents
    );
    $testConfigContents = str_replace('youremptytestdbnamehere', $_SERVER['DB_NAME'], $testConfigContents);
    $testConfigContents = str_replace('yourusernamehere', $_SERVER['DB_USER'], $testConfigContents);
    $testConfigContents = str_replace('yourpasswordhere', $_SERVER['DB_PASSWORD'], $testConfigContents);
    $testConfigContents = str_replace('localhost', $_SERVER['DB_HOST'], $testConfigContents);

    file_put_contents($testConfigPath, $testConfigContents);
}

require_once dirname(__FILE__, 2).'/wp/tests/phpunit/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
    // Force register autoloader
    if (! defined('REST_REQUEST')) {
        define('REST_REQUEST', true);
    }
    require dirname(__DIR__).'/fastendpoints-depends.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Give access to tests_add_filter() function.
require_once dirname(__FILE__, 2).'/wp/tests/phpunit/includes/functions.php';

require_once dirname(__DIR__).'/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

/*
 * Bootstrap WordPress. This will also load the Composer autoload file, the PHPUnit Polyfills
 * and the custom autoloader for the TestCase and the mock object classes.
 */
WPIntegration\bootstrap_it();

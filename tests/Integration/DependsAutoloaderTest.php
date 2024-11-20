<?php

/**
 * Holds integration tests for the DependsAutoloader
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Depends\Tests\Integration;

use Wp\FastEndpoints\Depends\DependsAutoloader;
use Wp\FastEndpoints\Depends\Tests\Helpers\Helpers;
use Yoast\WPTestUtils\WPIntegration\TestCase;

if (! Helpers::isIntegrationTest()) {
    return;
}

/*
 * We need to provide the base test class to every integration test.
 * This will enable us to use all the WordPress test goodies, such as
 * factories and proper test cleanup.
 */
uses(TestCase::class);

beforeEach(function () {
    parent::setUp();
    $autoloader = new DependsAutoloader(SAMPLE_CONFIG_FILEPATH);
    $autoloader->unregister();
    $autoloader->register();
});

afterEach(function () {
    parent::tearDown();
    delete_option('active_plugins');
    unset($_SERVER['REQUEST_URI']);
    unset($_SERVER['REQUEST_METHOD']);
});

test('Retrieves correct route dependencies', function () {
    update_option('active_plugins', ['buddypress/buddypress.php', 'my-plugin/my-plugin.php', 'another-plugin/my-plugin.php']);
    $_SERVER['REQUEST_URI'] = '/wp-json/my-plugin/v1/user';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    expect(get_option('active_plugins'))
        ->toEqual(['buddypress/buddypress.php', 'my-plugin/my-plugin.php']);
})->group('autoloader');

test('No request method. Retrieves all active plugins', function () {
    update_option('active_plugins', ['active-plugin']);
    $_SERVER['REQUEST_URI'] = '/wp-json/my-plugin/v1/user';
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    expect(get_option('active_plugins'))
        ->toEqual(['active-plugin']);
})->group('autoloader');

test('No request endpoint. Retrieves all active plugins', function () {
    update_option('active_plugins', ['active-plugin']);
    $_SERVER['REQUEST_URI'] = '/wp-json/doesnt-exist';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    expect(get_option('active_plugins'))
        ->toEqual(['active-plugin']);
})->group('autoloader');

test('Endpoint does not require any plugin', function () {
    update_option('active_plugins', ['active-plugin']);
    $_SERVER['REQUEST_URI'] = '/wp-json/my-plugin/v1/no-depends';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    expect(get_option('active_plugins'))
        ->toEqual([]);
})->group('autoloader');

test('Regex route. Retrieves correct dependencies', function () {
    update_option('active_plugins', ['my-plugin/my-plugin.php', 'buddypress/buddypress.php']);
    $_SERVER['REQUEST_URI'] = '/wp-json/my-plugin/v1/blog-post/1';
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    expect(get_option('active_plugins'))
        ->toEqual(['my-plugin/my-plugin.php']);
})->group('autoloader');

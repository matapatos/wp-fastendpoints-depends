<?php

/**
 * Holds integration tests for the DependenciesGenerator
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Depends\Tests\Integration;

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
});

afterEach(function () {
    parent::tearDown();
    delete_option('fastendpoints_dependencies');
    delete_option('active_plugins');
    unset($_SERVER['REQUEST_URI']);
    unset($_SERVER['REQUEST_METHOD']);
});

test('Retrieves correct route dependencies', function () {
    $routeDependencies = [
        'GET' => [
            '/custom/route' => ['custom-route', 'get', 'plugin-not-active'],
            '/fake-route' => [],
        ],
        'POST' => [
            '/custom/route' => ['custom-route', 'post'],
        ],
    ];
    update_option('fastendpoints_dependencies', $routeDependencies);
    update_option('active_plugins', ['my-plugin', 'custom-route', 'get']);
    $_SERVER['REQUEST_URI'] = '/custom/route';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    expect(get_option('active_plugins'))
        ->toEqual(['custom-route', 'get']);
})->group('autoloader');

test('No request method. Retrieves plugins all active plugins', function () {
    $routeDependencies = [
        'POST' => [
            '/custom/route' => ['custom-route', 'post'],
        ],
    ];
    update_option('fastendpoints_dependencies', $routeDependencies);
    update_option('active_plugins', ['my-plugin', 'custom-route', 'put']);
    $_SERVER['REQUEST_URI'] = '/custom/route';
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    expect(get_option('active_plugins'))
        ->toEqual(['my-plugin', 'custom-route', 'put']);
})->group('autoloader');

test('No route dependencies. Retrieves plugins all active plugins', function () {
    $routeDependencies = [
        'GET' => [
            '/custom/route' => ['custom-route', 'get', 'plugin-not-active'],
            '/fake-route' => [],
        ],
        'POST' => [
            '/custom/route' => ['custom-route', 'post'],
        ],
    ];
    update_option('fastendpoints_dependencies', $routeDependencies);
    update_option('active_plugins', ['active-plugin']);
    $_SERVER['REQUEST_URI'] = '/unknown/route';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    expect(get_option('active_plugins'))
        ->toEqual(['active-plugin']);
})->group('autoloader');

test('Regex route. Retrieves correct dependencies', function () {
    $routeDependencies = [
        'GET' => [
            '/custom/route/(?P<ID>[\d]+)' => ['custom-route', 'get', 'plugin-not-active'],
        ],
    ];
    update_option('fastendpoints_dependencies', $routeDependencies);
    update_option('active_plugins', ['custom-route', 'get']);
    $_SERVER['REQUEST_URI'] = '/custom/route/10';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    expect(get_option('active_plugins'))
        ->toEqual(['custom-route', 'get']);
})->group('autoloader');

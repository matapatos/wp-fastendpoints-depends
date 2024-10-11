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

use Wp\FastEndpoints\Depends\DependenciesGenerator;
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

    // Set up a REST server instance.
    global $wp_rest_server;
    $this->server = $wp_rest_server = new \WP_REST_Server;

    $fastEndpointsRouter = Helpers::getRouter('FastEndpointsRouter.php');
    $fastEndpointsRouter->register();

    include \ROUTERS_DIR.'NativeWpEndpoints.php';

    do_action('rest_api_init', $this->server);
});

afterEach(function () {
    global $wp_rest_server;
    $wp_rest_server = null;
    delete_option('active_plugins');

    parent::tearDown();
});

test('Generates dependencies for both native WP endpoints and FastEndpoints', function () {
    update_option('active_plugins', ['my-plugin/my-plugin.php', 'buddypress/buddypress.php']);

    $configFilePath = tempnam('/tmp', 'configs');
    $generator = new DependenciesGenerator($configFilePath);
    $generator->update();

    expect(file_exists($configFilePath))->toBeTrue();
    $dependencies = include $configFilePath;
    @unlink($configFilePath);
    expect($dependencies)
        ->toEqual([
            'GET' => [
                '/fast-endpoints/v1/(?P<ID>[\\d]+)' => ['my-plugin/my-plugin.php'],
                '/native/v1/custom' => ['my-plugin/my-plugin.php'],
            ],
            'POST' => [
                '/fast-endpoints/v1/(?P<ID>[\\d]+)' => ['buddypress/buddypress.php', 'my-plugin/my-plugin.php'],
                '/native/v1/custom' => ['my-plugin/my-plugin.php', 'buddypress/buddypress.php'],
            ],
        ]);
})->group('generator');

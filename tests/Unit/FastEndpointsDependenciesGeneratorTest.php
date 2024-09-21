<?php

/**
 * Holds tests for the DependsAutoloader class.
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Depends\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Wp\FastEndpoints\Depends\FastEndpointDependenciesGenerator;
use Wp\FastEndpoints\Depends\Tests\Helpers\Helpers;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

// register

test('Register generator', function () {
    $generator = new FastEndpointDependenciesGenerator;
    Functions\expect('register_activation_hook')
        ->once()
        ->with('/test/example.php', \Mockery::type('closure'));
    $generator->register('/test/example.php');
})->group('generator', 'register');

test('Register generator via CLI', function () {
    $generator = Mockery::mock(FastEndpointDependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isRunningCli')
        ->andReturn(true)
        ->getMock();
    Functions\expect('register_activation_hook')
        ->once()
        ->with('/test/example.php', Mockery::type('closure'));
    Mockery::mock('alias:WP_CLI')
        ->shouldReceive('add_command')
        ->once()
        ->with('fast_endpoints_depends_update', Mockery::type('closure'));
    $generator->register('/test/example.php');
})->group('generator', 'register');

// update

test('Add hooks to update dependencies', function () {
    $generator = new FastEndpointDependenciesGenerator;
    $generator->update();
    Actions\has('fastendpoints_after_register', $generator->routerRegistered(...));
    Filters\has('wp_loaded', $generator->updateDependenciesOption(...));
    // Avoid no assertions warning
    $this->assertTrue(true);
})->group('generator', 'update');

// routerRegistered

test('Registering FastEndpoints routers', function (int $numRouters) {
    $generator = new FastEndpointDependenciesGenerator;
    $allRegisteredRouters = Helpers::getNonPublicClassProperty($generator, 'allRegisteredRouters');
    expect($allRegisteredRouters)
        ->toBeArray()
        ->toBeEmpty();
    $registeredRouters = [];
    for ($i = 0; $i < $numRouters; $i++) {
        $router = Mockery::mock('alias:Wp\FastEndpoints\Contracts\Http\Router');
        $generator->routerRegistered($router);
        $registeredRouters[] = $router;
    }
    $allRegisteredRouters = Helpers::getNonPublicClassProperty($generator, 'allRegisteredRouters');
    expect($allRegisteredRouters)
        ->toHaveCount($numRouters)
        ->toMatchArray($registeredRouters);
})->group('generator', 'routerRegistered')->with([1, 5]);

test('Registering FastEndpoints routers via CLI', function (int $numRouters) {
    Mockery::mock('alias:WP_CLI')
        ->shouldReceive('debug')
        ->times($numRouters)
        ->with('Detected router with 2 endpoints');

    $generator = Mockery::mock(FastEndpointDependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isRunningCli')
        ->andReturn(true)
        ->getMock();

    $allRegisteredRouters = Helpers::getNonPublicClassProperty($generator, 'allRegisteredRouters');
    expect($allRegisteredRouters)
        ->toBeArray()
        ->toBeEmpty();
    $registeredRouters = [];
    for ($i = 0; $i < $numRouters; $i++) {
        $router = Mockery::mock('alias:Wp\FastEndpoints\Contracts\Http\Router')
            ->shouldReceive('getEndpoints')
            ->andReturn([1, 2])
            ->getMock();

        $generator->routerRegistered($router);
        $registeredRouters[] = $router;
    }
    $allRegisteredRouters = Helpers::getNonPublicClassProperty($generator, 'allRegisteredRouters');
    expect($allRegisteredRouters)
        ->toHaveCount($numRouters)
        ->toMatchArray($registeredRouters);
})->group('generator', 'routerRegistered')->with([1, 5]);

// updateDependenciesOption

test('Updates dependencies option', function () {
    Functions\expect('path_join')
        ->andReturnUsing(function ($val1, $val2) {
            return "$val1/$val2";
        });

    Functions\expect('wp_get_active_and_valid_plugins')
        ->once()
        ->andReturn([
            '/wp/plugins/buddypress/plugin.php',
            '/wp/plugins/seo/plugin.php',
            '/wp/plugins/my-plugin/plugin.php',
        ]);

    Functions\expect('update_option')
        ->once()
        ->with('fastendpoints_dependencies', [
            'GET' => ['/buddypress' => ['/wp/plugins/buddypress/plugin.php'], '/seo' => ['/wp/plugins/seo/plugin.php'], '/empty' => []],
            'POST' => ['/my-plugin' => ['/wp/plugins/buddypress/plugin.php', '/wp/plugins/my-plugin/plugin.php']],
        ]);

    $generator = Mockery::mock(FastEndpointDependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getDependenciesFromRouters')
        ->andReturn([
            'GET' => ['/buddypress' => ['buddypress'], '/seo' => ['/wp/plugins/seo/plugin.php'], '/empty' => []],
            'POST' => ['/my-plugin' => ['buddypress', 'my-plugin']],
        ])
        ->getMock();

    $generator->updateDependenciesOption();
})->group('generator', 'updateDependenciesOption');

test('Updates dependencies option via CLI', function () {
    Mockery::mock('alias:WP_CLI')
        ->shouldReceive('debug')
        ->times(3)
        ->getMock()
        ->shouldReceive('success')
        ->with('Successfully updated dependencies for 0 routers');

    Functions\expect('WP_CLI\Utils\format_items')
        ->once();

    Functions\expect('path_join')
        ->andReturnUsing(function ($val1, $val2) {
            return "$val1/$val2";
        });

    Functions\expect('wp_get_active_and_valid_plugins')
        ->once()
        ->andReturn([
            '/wp/plugins/buddypress/plugin.php',
            '/wp/plugins/seo/plugin.php',
            '/wp/plugins/my-plugin/plugin.php',
        ]);

    Functions\expect('update_option')
        ->once()
        ->with('fastendpoints_dependencies', [
            'GET' => ['/buddypress' => ['/wp/plugins/buddypress/plugin.php'], '/seo' => ['seo.php'], '/empty' => []],
            'POST' => ['/my-plugin' => ['/wp/plugins/buddypress/plugin.php', '/wp/plugins/my-plugin/plugin.php']],
        ]);

    $generator = Mockery::mock(FastEndpointDependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getDependenciesFromRouters')
        ->andReturn([
            'GET' => ['/buddypress' => ['buddypress'], '/seo' => ['seo.php'], '/empty' => []],
            'POST' => ['/my-plugin' => ['buddypress', 'my-plugin']],
        ])
        ->getMock()
        ->shouldReceive('isRunningCli')
        ->andReturn(true)
        ->getMock();

    $generator->updateDependenciesOption();
})->group('generator', 'updateDependenciesOption');

// getDependenciesFromRouters

test('Retrieving dependencies from routers', function () {
    $generator = new FastEndpointDependenciesGenerator;
    $firstRouter = Mockery::mock('alias:Wp\FastEndpoints\Contracts\Http\Router')
        ->shouldReceive('getEndpoints')
        ->once()
        ->andReturn($firstRouterEndpoints)
        ->getMock()
        ->shouldReceive('getSubRouters')
        ->once()
        ->andReturn([]);
    $secondRouter = Mockery::mock('alias:Wp\FastEndpoints\Contracts\Http\Router')
        ->shouldReceive('getEndpoints')
        ->once()
        ->andReturn($secondRouterEndpoints)
        ->getMock()
        ->shouldReceive('getSubRouters')
        ->once()
        ->andReturn([]);
    $routers = [

    ];
    $dependencies = Helpers::invokeNonPublicClassMethod($generator, 'getDependenciesFromRouters', $routers);
})->group('generator', 'getDependenciesFromRouters');

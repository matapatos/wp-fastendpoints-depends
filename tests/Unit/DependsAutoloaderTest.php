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
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Wp\FastEndpoints\Depends\DependsAutoloader;
use Wp\FastEndpoints\Depends\Tests\Helpers\Helpers;

beforeEach(function () {
    Monkey\setUp();
    $autoloader = new DependsAutoloader;
    Helpers::setNonPublicClassProperty($autoloader, 'instance', null);
    unset($_SERVER['REQUEST_METHOD']);
    unset($_GET['rest_route']);
});

afterEach(function () {
    Monkey\tearDown();
});

dataset('http_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']);

// Constructor

test('Creating DependsAutoloader instance', function () {
    $autoloader = new DependsAutoloader;
    expect($autoloader)
        ->toBeInstanceOf(DependsAutoloader::class)
        ->and(Helpers::getNonPublicClassProperty($autoloader, 'instance'))
        ->toBeNull();
})->group('autoloader', 'constructor');

// Can register?

test('Can register autoloader', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isRestRequest')
        ->once()
        ->andReturn(true)
        ->getMock();
    Functions\when('is_blog_installed')->justReturn(true);
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'canRegister'))->toBeTrue();
})->group('autoloader', 'canRegister');

test('Avoid registering autoloader when it is not a REST request', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isRestRequest')
        ->once()
        ->andReturn(false)
        ->getMock();
    Functions\when('is_blog_installed')->justReturn(true);
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'canRegister'))->toBeFalse();
})->group('autoloader', 'canRegister');

test('Avoid registering autoloader when blog is not installed', function () {
    $autoloader = new DependsAutoloader;
    Functions\when('is_blog_installed')->justReturn(false);
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'canRegister'))->toBeFalse();
})->group('autoloader', 'canRegister');

test('Avoid registering autoloader multiple times', function () {
    $autoloader = new DependsAutoloader;
    Helpers::setNonPublicClassProperty($autoloader, 'instance', $autoloader);
    Functions\when('is_blog_installed')->justReturn(true);
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'canRegister'))->toBeFalse();
})->group('autoloader', 'canRegister');

test('Avoid registering autoloader when enabled flag is false', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isDisabled')
        ->once()
        ->andReturn(true)
        ->getMock();
    Functions\when('is_blog_installed')->justReturn(true);
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'canRegister'))->toBeFalse();
})->group('autoloader', 'canRegister');

// getFastEndpointDependencies

test('Retrieving correct dependencies', function (string $method) {
    $autoloader = new DependsAutoloader;
    $_SERVER['REQUEST_METHOD'] = $method;
    $allDependencies = [
        'GET' => ['get', 'hello', 'world'],
        'POST' => ['post', 'hello', '123'],
        'PUT' => ['put', 'hello'],
        'PATCH' => ['patch', 'hello', 'world'],
        'DELETE' => ['delete', 'world'],
        'HEAD' => [],
        'OPTIONS' => ['options', 'hello', '789'],
        'FAKE_METHOD' => ['should-never-return-this'],
    ];
    Functions\expect('get_option')
        ->once()
        ->with('fastendpoints_dependencies')
        ->andReturn($allDependencies);
    $dependencies = Helpers::invokeNonPublicClassMethod($autoloader, 'getFastEndpointDependencies');
    expect($dependencies)
        ->toEqual($allDependencies[$method]);
})->with('http_methods')->group('autoloader', 'getFastEndpointDependencies');

test('No dependencies saved', function () {
    $autoloader = new DependsAutoloader;
    Functions\expect('get_option')
        ->once()
        ->with('fastendpoints_dependencies')
        ->andReturn([]);
    $dependencies = Helpers::invokeNonPublicClassMethod($autoloader, 'getFastEndpointDependencies');
    expect($dependencies)
        ->toBeArray()
        ->toBeEmpty();
})->group('autoloader', 'getFastEndpointDependencies');

test('Unavailable HTTP method', function (string $method) {
    $autoloader = new DependsAutoloader;
    $_SERVER['REQUEST_METHOD'] = $method;
    Functions\expect('get_option')
        ->once()
        ->with('fastendpoints_dependencies')
        ->andReturn(['FAKE_HTTP_METHOD' => ['should-never-return-this']]);
    $dependencies = Helpers::invokeNonPublicClassMethod($autoloader, 'getFastEndpointDependencies');
    expect($dependencies)
        ->toBeArray()
        ->toBeEmpty();
})->with('http_methods')->group('autoloader', 'getFastEndpointDependencies');

// discardUnnecessaryPlugins

test('Discards unnecessary plugins', function (array $activePlugins) {
    $expectedPlugins = array_values(array_intersect($activePlugins, ['my-plugin']));
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $autoloader
        ->shouldReceive('getFastEndpointDependencies')
        ->once()
        ->andReturn([
            '/wp-json/example/v2/sample/60' => ['should-not-trigger-this'],
            '/wp-json/example/v2/sample/(?P<ID>[\d]+)' => ['my-plugin'],
        ]);
    $autoloader
        ->shouldReceive('getCurrentRequestUrlPath')
        ->once()
        ->andReturn('/wp-json/example/v2/sample/'.rand(0, 50));

    expect($autoloader->discardUnnecessaryPlugins($activePlugins))
        ->toEqual($expectedPlugins);
})->with([
    [['my-plugin', 'hello']],
    [['another']],
    [['myspanishnow', 'my-plugin', 'hello-world']],
])->group('autoloader', 'discardUnnecessaryPlugins');

test('Discards all plugins', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $autoloader
        ->shouldReceive('getFastEndpointDependencies')
        ->once()
        ->andReturn([
            '/wp-json/example/v2/sample/10' => [],
            '/wp-json/example/v2/sample/hey' => ['should-not-trigger-this'],
        ]);
    $autoloader
        ->shouldReceive('getCurrentRequestUrlPath')
        ->once()
        ->andReturn('/wp-json/example/v2/sample/10');

    expect($autoloader->discardUnnecessaryPlugins(['should-not-trigger-this']))
        ->toBeArray()
        ->toBeEmpty();
})->group('autoloader', 'discardUnnecessaryPlugins');

test('Ignores discarding if no plugins are active', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldNotReceive('getFastEndpointDependencies')
        ->getMock();
    expect($autoloader->discardUnnecessaryPlugins([]))
        ->toBeArray()
        ->toBeEmpty();
})->group('autoloader', 'discardUnnecessaryPlugins');

test('Ignores discarding if no rest path', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $autoloader
        ->shouldReceive('getFastEndpointDependencies')
        ->once()
        ->andReturn([
            '/wp-json/example/v2/sample/10' => [],
        ]);
    $autoloader
        ->shouldReceive('getCurrentRequestUrlPath')
        ->once()
        ->andReturn(null);

    expect($autoloader->discardUnnecessaryPlugins(['hello-world']))
        ->toEqual(['hello-world']);
})->group('autoloader', 'discardUnnecessaryPlugins');

test('No matching routes found to discard plugins', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $autoloader
        ->shouldReceive('getFastEndpointDependencies')
        ->once()
        ->andReturn([
            '/wp-json/example/v2/sample/fake' => ['should-not-trigger-this'],
            '/wp-json/yup/v2/sample/hey' => ['should-not-trigger-this'],
        ]);
    $autoloader
        ->shouldReceive('getCurrentRequestUrlPath')
        ->once()
        ->andReturn('/wp-json/example/v2/sample/10');

    expect($autoloader->discardUnnecessaryPlugins(['hello-world']))
        ->toEqual(['hello-world']);
})->group('autoloader', 'discardUnnecessaryPlugins');

// Register

test('Registering autoloader', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('canRegister')
        ->once()
        ->andReturn(true)
        ->getMock();
    expect(Helpers::getNonPublicClassProperty($autoloader, 'instance'))->toBeNull();
    $autoloader->register();
    expect(Helpers::getNonPublicClassProperty($autoloader, 'instance'))
        ->toBe($autoloader)
        ->and(Filters\has('option_active_plugins', $autoloader->discardUnnecessaryPlugins(...)))
        ->toBe(10);
})->group('autoloader', 'register');

test('Avoid register autoloader when unable', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('canRegister')
        ->once()
        ->andReturn(false)
        ->getMock();
    $autoloader->register();
    expect(Helpers::getNonPublicClassProperty($autoloader, 'instance'))
        ->toBeNull()
        ->and(Filters\has('option_active_plugins', $autoloader->discardUnnecessaryPlugins(...)))
        ->toBeFalse();
})->group('autoloader', 'register');

// isRestRequest

test('Is a REST request when the WordPress flag is enabled', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isWpRestRequestFlagEnabled')
        ->once()
        ->andReturn(true)
        ->getMock();
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'isRestRequest'))
        ->toBeTrue();
})->group('autoloader', 'register');

test('Is a REST request when rest_route is fulfilled', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isWpRestRequestFlagEnabled')
        ->once()
        ->andReturn(false)
        ->getMock();
    $_GET['rest_route'] = '/wp-json/example/v2/sample/10';
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'isRestRequest'))
        ->toBeTrue();
})->group('autoloader', 'register');

test('Is a REST request when route matches a REST path', function () {
    Functions\when('rest_url')->justReturn(['path' => '/wp-json']);
    Functions\when('trailingslashit')->returnArg();
    Functions\when('wp_parse_url')->returnArg();
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $autoloader
        ->shouldReceive('isWpRestRequestFlagEnabled')
        ->once()
        ->andReturn(false);
    $autoloader
        ->shouldReceive('getCurrentRequestUrlPath')
        ->once()
        ->andReturn('/wp-json/example/v2/sample/10');
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'isRestRequest'))
        ->toBeTrue();
})->group('autoloader', 'register');

test('Is not a REST request when no path is defined', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $autoloader
        ->shouldReceive('getCurrentRequestUrlPath')
        ->once()
        ->andReturn(null);
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'isRestRequest'))
        ->toBeFalse();
})->group('autoloader', 'register');

// getCurrentRequestUrlPath

test('Retrieves correct URL path', function (?string $path) {
    Functions\when('add_query_arg')->justReturn(['path' => $path]);
    Functions\when('wp_parse_url')->returnArg();
    Mockery::mock('alias:WP_Rewrite');
    $autoloader = new DependsAutoloader;
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'getCurrentRequestUrlPath'))
        ->toBe($path);
})->with(['/my-path', null])->group('autoloader', 'getCurrentRequestUrlPath');

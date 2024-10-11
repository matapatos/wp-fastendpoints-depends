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
use Brain\Monkey\Functions;
use Mockery;
use Wp\FastEndpoints\Depends\DependenciesGenerator;
use Wp\FastEndpoints\Depends\DependsCommand;
use Wp\FastEndpoints\Depends\Tests\Helpers\Helpers;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

// register

test('Register generator', function () {
    $generator = new DependenciesGenerator;
    Functions\expect('register_activation_hook')
        ->once()
        ->with('/test/example.php', \Mockery::type('closure'));
    $generator->register('/test/example.php');
})->group('generator', 'register');

test('Register generator via CLI', function () {
    $generator = Mockery::mock(DependenciesGenerator::class)
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
        ->with('fastendpoints', DependsCommand::class);
    $generator->register('/test/example.php');
})->group('generator', 'register');

// update

test('Add hooks to update dependencies', function () {
    $generator = new DependenciesGenerator;
    $generator->update();
    expect(Actions\has('wp_loaded', $generator->updateDependenciesConfig(...)))
        ->toBe(10);
})->group('generator', 'update');

test('Update dependencies when WordPress is already loaded', function () {
    Functions\expect('did_action')
        ->once()
        ->with('wp_loaded')
        ->andReturn(true);

    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldReceive('updateDependenciesConfig')
        ->once()
        ->getMock();
    $generator->update();
    expect(Actions\has('wp_loaded', $generator->updateDependenciesConfig(...)))
        ->toBeFalsy();
})->group('generator', 'update');

// getDependenciesPluginFullPath

test('Retrieve plugins full path from slug', function (bool $isRunningCli) {
    Mockery::mock('alias:WP_CLI')
        ->shouldReceive('debug')
        ->times($isRunningCli ? 1 : 0)
        ->with('[GET] /test updating dependency from seo to /wp/plugins/seo/plugin.php');

    Functions\expect('get_option')
        ->once()
        ->andReturn([
            '/wp/plugins/buddypress/plugin.php',
            '/wp/plugins/seo/plugin.php',
            '/wp/plugins/my-plugin/plugin.php',
        ]);

    Functions\expect('path_join')
        ->andReturnUsing(function ($val1, $val2) {
            return "$val1/$val2";
        });

    $dependencies = ['/wp/plugins/buddypress/plugin.php', 'seo', 'unknown'];
    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isRunningCli')
        ->andReturn($isRunningCli)
        ->getMock();
    $fullPathDependencies = Helpers::invokeNonPublicClassMethod($generator, 'getDependenciesPluginFullPath', $dependencies, 'GET', '/test');
    expect($fullPathDependencies)
        ->toBe([
            '/wp/plugins/buddypress/plugin.php',
            '/wp/plugins/seo/plugin.php',
        ]);
})->with([true, false])->group('generator', 'getDependenciesPluginFullPath');

// updateDependenciesConfig

test('Update dependencies option', function () {
    $dependencies = [
        'GET' => ['/buddypress' => ['/wp/plugins/buddypress/plugin.php'], '/seo' => ['/wp/plugins/seo/plugin.php'], '/empty' => []],
        'POST' => ['/my-plugin' => ['/wp/plugins/buddypress/plugin.php', '/wp/plugins/my-plugin/plugin.php']],
    ];

    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getRoutesDependencies')
        ->andReturn($dependencies)
        ->getMock()
        ->shouldReceive('saveConfigFile')
        ->once()
        ->with($dependencies)
        ->getMock();

    $generator->updateDependenciesConfig();
})->group('generator', 'updateDependenciesConfig');

test('Update dependencies option via CLI', function () {
    Mockery::mock('alias:WP_CLI')
        ->shouldReceive('success')
        ->with('REST route dependencies updated');

    $dependencies = [
        'GET' => ['/buddypress' => ['buddypress'], '/seo' => ['seo.php'], '/empty' => []],
        'POST' => ['/my-plugin' => ['buddypress', 'my-plugin']],
    ];
    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getRoutesDependencies')
        ->andReturn($dependencies)
        ->getMock()
        ->shouldReceive('saveConfigFile')
        ->once()
        ->with($dependencies)
        ->getMock()
        ->shouldReceive('isRunningCli')
        ->andReturn(true)
        ->getMock();

    $generator->updateDependenciesConfig();
})->group('generator', 'updateDependenciesConfig');

// allRestRoutes

test('Retrieve all REST routes', function () {
    $restServerMock = Mockery::mock('alias:WP_REST_Server')
        ->shouldReceive('get_routes')
        ->times(3)
        ->andReturnUsing(function (?string $namespace = null) {
            if (! $namespace === null) {
                return [1, 2, 3, 4];
            }

            return [
                "/$namespace/depends" => [[
                    'methods' => ['GET' => true],
                    'depends' => ['buddypress', 'seo'],
                    'accept_json' => false,
                ]],
                "/$namespace/depends/empty" => [[
                    'methods' => ['PATCH' => true],
                    'depends' => [],
                ]],
                "/$namespace/no-depends" => [[
                    'methods' => ['PUT' => true, 'POST' => true, 'DELETE' => false],
                ]],
            ];
        })
        ->getMock()
        ->shouldReceive('get_namespaces')
        ->once()
        ->andReturn(['test/v1', 'test/v2'])
        ->getMock();

    Functions\expect('rest_get_server')
        ->once()
        ->andReturn($restServerMock);

    $generator = new DependenciesGenerator;
    $allRestRoutes = Helpers::invokeNonPublicClassMethod($generator, 'allRestRoutes');
    $allRestRoutes = iterator_to_array($allRestRoutes);
    expect($allRestRoutes)
        ->toBe([
            // test/v1
            ['method' => 'GET', 'route' => '/test/v1/depends', 'depends' => ['buddypress', 'seo']],
            ['method' => 'PATCH', 'route' => '/test/v1/depends/empty', 'depends' => []],
            ['method' => 'PUT', 'route' => '/test/v1/no-depends', 'depends' => null],
            ['method' => 'POST', 'route' => '/test/v1/no-depends', 'depends' => null],
            // test/v2
            ['method' => 'GET', 'route' => '/test/v2/depends', 'depends' => ['buddypress', 'seo']],
            ['method' => 'PATCH', 'route' => '/test/v2/depends/empty', 'depends' => []],
            ['method' => 'PUT', 'route' => '/test/v2/no-depends', 'depends' => null],
            ['method' => 'POST', 'route' => '/test/v2/no-depends', 'depends' => null],
        ]);
})->group('generator', 'allRestRoutes');

// getRoutesDependencies

test('Retrieve the dependencies of each REST route', function () {
    $dependencies = [
        // test/v1
        ['method' => 'GET', 'route' => '/test/v1/depends', 'depends' => ['buddypress', 'seo']],
        ['method' => 'PATCH', 'route' => '/test/v1/depends/empty', 'depends' => []],
        ['method' => 'PUT', 'route' => '/test/v1/no-depends', 'depends' => null],
        ['method' => 'POST', 'route' => '/test/v1/no-depends', 'depends' => null],
        // test/v2
        ['method' => 'GET', 'route' => '/test/v2/depends', 'depends' => ['buddypress', 'seo']],
        ['method' => 'PATCH', 'route' => '/test/v2/depends/empty', 'depends' => []],
        ['method' => 'PUT', 'route' => '/test/v2/no-depends', 'depends' => null],
        ['method' => 'POST', 'route' => '/test/v2/no-depends', 'depends' => null],
    ];

    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('allRestRoutes')
        ->once()
        ->andReturn($dependencies)
        ->getMock()
        ->shouldReceive('getDependenciesPluginFullPath')
        ->andReturnUsing(function (array $dependencies, string $method, string $route) {
            return array_map(function ($value) {
                return "$value.php";
            }, $dependencies);
        })
        ->getMock();
    $routeDependencies = Helpers::invokeNonPublicClassMethod($generator, 'getRoutesDependencies');
    expect($routeDependencies)
        ->toBe([
            'GET' => [
                '/test/v1/depends' => ['buddypress.php', 'seo.php'],
                '/test/v2/depends' => ['buddypress.php', 'seo.php'],
            ],
            'PATCH' => [
                '/test/v1/depends/empty' => [],
                '/test/v2/depends/empty' => [],
            ],
        ]);
})->group('generator', 'getRoutesDependencies');

test('Invalid REST route dependencies', function (bool $isRunningCli) {
    Mockery::mock('alias:WP_CLI')
        ->shouldReceive('warning')
        ->times($isRunningCli ? 1 : 0)
        ->with('[GET] /test Invalid dependencies. Expecting either null or an array but boolean given');

    $dependencies = [
        ['method' => 'GET', 'route' => '/test', 'depends' => false],
    ];
    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('allRestRoutes')
        ->once()
        ->andReturn($dependencies)
        ->getMock()
        ->shouldReceive('isRunningCli')
        ->andReturn($isRunningCli)
        ->getMock();

    $routeDependencies = Helpers::invokeNonPublicClassMethod($generator, 'getRoutesDependencies');
    expect($routeDependencies)
        ->toBe([]);
})->with([true, false])->group('generator', 'getRoutesDependencies');

// getProgressBar

test('Retrieve progress bar', function () {
    Functions\expect('WP_CLI\Utils\make_progress_bar')
        ->once()
        ->with('<message>', 5)
        ->andReturn('<progress-bar>');

    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('isRunningCli')
        ->andReturn(true)
        ->getMock();

    $progressBar = Helpers::invokeNonPublicClassMethod($generator, 'getProgressBar', '<message>', 5);
    expect($progressBar)
        ->toBe('<progress-bar>');
})->group('generator', 'getProgressBar');

// saveConfigFile

test('Saves dependencies config', function () {
    $configFilePath = tempnam('/tmp', 'configs');
    file_put_contents($configFilePath, '<?php return ["old-dependencies"]');

    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getConfigFilePath')
        ->andReturn($configFilePath)
        ->getMock();

    $dependencies = [
        'GET' => [
            '/test/v1/hey' => ['buddypress.php', 'seo.php'],
        ],
    ];
    Helpers::invokeNonPublicClassMethod($generator, 'saveConfigFile', $dependencies);
    expect(file_exists($configFilePath))->toBeTrue();
    $routeDependencies = include $configFilePath;
    @unlink($configFilePath);
    expect($routeDependencies)->toEqual($dependencies);
})->group('generator', 'saveConfigFile');

test('Removes dependencies config if no dependencies are defined', function ($filePath) {
    if (! is_string($filePath)) {
        $filePath = stream_get_meta_data($filePath)['uri'];
    }
    $generator = Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('getConfigFilePath')
        ->andReturn($filePath)
        ->getMock();

    Helpers::invokeNonPublicClassMethod($generator, 'saveConfigFile', []);
    expect(file_exists($filePath))->toBeFalse();
})->with([tmpfile(), '/does/not/exist.php'])->group('generator', 'saveConfigFile');

// getConfigFilePath

test('Retrieve config file path', function () {
    Functions\expect('plugin_dir_path')
        ->once()
        ->andReturn('/plugin/src');

    $generator = new DependenciesGenerator;
    expect($generator->getConfigFilePath())
        ->toBe('/plugin/src/../config.php');
})->group('generator', 'getConfigFilePath');

test('Retrieve config file path via constant', function () {
    define('FASTENDPOINTS_DEPENDS_CONFIG_FILEPATH', '/plugin/config.php');

    $generator = new DependenciesGenerator;
    expect($generator->getConfigFilePath())
        ->toBe('/plugin/config.php');
})->group('generator', 'getConfigFilePath');

test('Retrieve config file path via argument', function () {
    $autoloader = new DependenciesGenerator('/custom/config.php');
    expect(Helpers::invokeNonPublicClassMethod($autoloader, 'getConfigFilePath'))
        ->toBe('/custom/config.php');
})->group('generator', 'getConfigFilePath');

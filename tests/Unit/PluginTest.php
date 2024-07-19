<?php

/**
 * Holds tests regarding plugin logic
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Depends\Tests\Unit;

use Brain\Monkey;
use Wp\FastEndpoints\Depends\DependsAutoloader;
use Wp\FastEndpoints\Depends\FastEndpointDependenciesGenerator;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

test('Check composer type', function () {
    $composerContents = file_get_contents(\PLUGIN_ROOT_DIR.'/composer.json');
    expect(json_decode($composerContents, true))
        ->toBeArray()
        ->toHaveKey('type', 'wordpress-muplugin');
})->group('plugin', 'composer');

test('Registering both autoloader and generator', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->shouldReceive('register')
        ->getMock();
    $generator = \Mockery::mock(FastEndpointDependenciesGenerator::class)
        ->shouldReceive('register')
        ->withArgs(function (string $filepath) {
            return file_exists($filepath) && str_ends_with($filepath, 'fastendpoints-depends.php');
        })
        ->getMock();
    require_once \PLUGIN_ROOT_DIR.'/fastendpoints-depends.php';
    // Avoid "Test did not perform any assertions" message
    expect(true)->toBeTrue();
})->group('plugin', 'register-autoloader');

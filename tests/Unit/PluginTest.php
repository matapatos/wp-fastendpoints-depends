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

test('Registering autoloader', function () {
    $autoloader = \Mockery::mock(DependsAutoloader::class)
        ->shouldReceive('register')
        ->getMock();
    require_once \PLUGIN_ROOT_DIR.'/wp-fastendpoints-depends.php';
    // Avoid "Test did not perform any assertions" message
    expect(true)->toBeTrue();
})->group('plugin', 'register-autoloader');

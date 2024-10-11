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
use Wp\FastEndpoints\Depends\DependenciesGenerator;
use Wp\FastEndpoints\Depends\DependsCommand;

beforeEach(function () {
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

// update

test('Update dependencies sub-command', function () {
    $generator = \Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldReceive('update')
        ->once()
        ->getMock();
    $command = new DependsCommand($generator);
    $command->depends();
})->group('command', 'depends');

// _clear

test('Clears dependencies sub-command', function () {
    \Mockery::mock('alias:WP_CLI')
        ->shouldReceive('success')
        ->once()
        ->with('REST route dependencies cleared');

    $tmpFile = tmpfile();
    $tmpFilePath = stream_get_meta_data($tmpFile)['uri'];
    $generator = \Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldReceive('getConfigFilePath')
        ->once()
        ->andReturn($tmpFilePath)
        ->getMock();
    $command = new DependsCommand($generator);
    $command->_clear();

    expect(file_exists($tmpFilePath))->toBeFalse();
})->group('command', 'depends-clear');

test('Clears dependencies sub-command when no configs exist', function () {
    \Mockery::mock('alias:WP_CLI')
        ->shouldReceive('success')
        ->once()
        ->with('REST route dependencies cleared');

    $generator = \Mockery::mock(DependenciesGenerator::class)
        ->makePartial()
        ->shouldReceive('getConfigFilePath')
        ->once()
        ->andReturn('/doesnt-exist/config.php')
        ->getMock();
    $command = new DependsCommand($generator);
    $command->_clear();
})->group('command', 'depends-clear');

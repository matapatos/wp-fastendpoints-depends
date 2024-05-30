<?php

/**
 * Plugin Name: Fast Endpoints Depends
 * Plugin URI:  https://github.com/matapatos/wp-fastendponts-depends
 * Description: Speed up your REST endpoints by using plugins like dependencies
 * Version:     1.0.0
 * Author:      André Gil
 * Author URI:
 *
 * @version 1.0.0
 *
 * @license MIT
 */
$composer = __DIR__.'/vendor/autoload.php';
if (! file_exists($composer)) {
    wp_die(
        esc_html__(
            'Error locating autoloader in plugins/wp-fastendpoints-depends. Please run <code>composer install</code>.',
            'fastendpoints-depends',
        ),
    );
}

require_once $composer;

$autoloader = new \Wp\FastEndpoints\Depends\DependsAutoloader();
$autoloader->register();

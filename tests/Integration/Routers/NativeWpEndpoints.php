<?php

/**
 * Holds an example of FastEndpoints router
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

add_action('rest_api_init', function () {
    register_rest_route('native/v1', 'custom', [
        'methods' => 'GET',
        'callback' => function () {
            return 'Only requires my-plugin';
        },
        'depends' => ['my-plugin'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('native/v1', 'custom', [
        'methods' => 'POST',
        'callback' => function () {
            return 'Requires both my-plugin and buddypress';
        },
        'depends' => ['my-plugin', 'buddypress'],
        'permission_callback' => '__return_true',
    ]);
});

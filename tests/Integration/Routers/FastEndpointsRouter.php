<?php

/**
 * Holds an example of FastEndpoints router
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

use Wp\FastEndpoints\Router;

$router = new Router('fast-endpoints', 'v1');
$router->depends(['my-plugin']);

$router->get('(?P<ID>[\d]+)', function () {
    return 'Only requires my-plugin';
});

$router->post('(?P<ID>[\d]+)', function () {
    return 'Requires both my-plugin and buddypress';
})
    ->depends(['buddypress']);

return $router;

<?php

/**
 * Holds class overrides from WordPress
 *
 * @since 0.9.0
 */

use Wp\FastEndpoints\Depends\Tests\Helpers\Helpers;

if (Helpers::isIntegrationTest()) {
    return;
}

if (! class_exists('WP_Rewrite')) {
    class WP_Rewrite
    {
    }
}

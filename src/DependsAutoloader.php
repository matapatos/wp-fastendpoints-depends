<?php

namespace Wp\FastEndpoints\Depends;

use WP_Rewrite;

/**
 * Holds logic to load plugins as dependencies for REST endpoints
 * @author
 * @link https://roots.io/
 */
class DependsAutoloader
{
    /** @var static Singleton instance */
    private static DependsAutoloader $instance;

    /**
     * Prepares the plugins needed for a given REST endpoint
     *
     * @return void
     */
    public function load(): void
    {
        if (!$this->canLoad()) {
            return;
        }

        self::$instance = $this;
        add_filter('option_active_plugins', $this->getActivePlugins(...));
    }

    /**
     * Retrieves the needed
     * @internal
     */
    public function getActivePlugins(mixed $value)
    {
        var_dump($value);
        var_dump('yUUUUUUUUUUUUUUUUUUUUPP');
        return $value;
    }

    /**
     * Checks if autoloader can be loaded
     * @return bool
     */
    protected function canLoad(): bool
    {
        // Already loaded?
        if (isset(self::$instance)) {
            return false;
        }

        // Is it disabled?
        if (defined('FASTENDPOINTS_DEPENDS_ENABLED') && !\FASTENDPOINTS_DEPENDS_ENABLED) {
            return false;
        }

        return $this->isRestRequest();
    }

    /**
     * Checks if a given request is a REST request.
     *
     * @link https://wordpress.stackexchange.com/questions/221202/does-something-like-is-rest-exist#answer-317041
     *
     * @author matzeeable
     */
    protected function isRestRequest(): bool
    {
        if (defined('REST_REQUEST') && \REST_REQUEST) {
            return true;
        }

        if (isset($_GET['rest_route']) && str_starts_with($_GET['rest_route'], '/')) {
            return true;
        }

        global $wp_rewrite;
        if ($wp_rewrite === null) {
            $wp_rewrite = new WP_Rewrite();
        }

        $restUrl = wp_parse_url(trailingslashit(rest_url()));
        $currentUrl = wp_parse_url(add_query_arg([]));

        return str_starts_with($currentUrl['path'] ?? '/', $restUrl['path']);
    }
}

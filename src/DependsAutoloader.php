<?php

namespace Wp\FastEndpoints\Depends;

use WP_Rewrite;

/**
 * Holds logic to load plugins as dependencies for REST endpoints
 *
 * @author André Gil <andre_gil22@hotmail.com>
 *
 * @link https://github.com/matapatos/wp-fastendponts-depends
 */
class DependsAutoloader
{
    /** @var static Singleton instance */
    private static DependsAutoloader $instance;

    /**
     * Prepares the plugins needed for a given REST endpoint
     */
    public function load(): void
    {
        if (! $this->canLoad()) {
            return;
        }

        self::$instance = $this;
        add_filter('option_active_plugins', $this->discardUnnecessaryPlugins(...));
    }

    /**
     * Discards unnecessary plugins from a FastEndpoint
     *
     * @internal
     */
    public function discardUnnecessaryPlugins(array $activePlugins)
    {
        if (! $activePlugins) {
            return $activePlugins;
        }

        $fastEndpointsDependencies = $this->getFastEndpointDependencies();
        foreach ($fastEndpointsDependencies as $route => $routeDependencies) {
            if (! preg_match('@^'.$route.'$@i', $_SERVER['REQUEST_URI'], $matches)) {
                continue;
            }

            return array_intersect($activePlugins, $routeDependencies);
        }

        return $activePlugins;
    }

    /**
     * Retrieves the dependencies needed for a given endpoint
     *
     * @array<string>
     *
     * @return array<string,array<string>>
     */
    protected function getFastEndpointDependencies(): array
    {
        $allEndpoints = get_option('fastendpoints_dependencies');
        if (! $allEndpoints) {
            return [];
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if (! isset($allEndpoints[$method])) {
            return [];
        }

        return $allEndpoints[$method];
    }

    /**
     * Checks if autoloader can be loaded
     */
    protected function canLoad(): bool
    {
        // Already loaded?
        if (isset(self::$instance)) {
            return false;
        }

        // Is it disabled?
        if (defined('FASTENDPOINTS_DEPENDS_ENABLED') && ! \FASTENDPOINTS_DEPENDS_ENABLED) {
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

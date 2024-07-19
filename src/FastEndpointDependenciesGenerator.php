<?php

namespace Wp\FastEndpoints\Depends;

use WP_CLI;

/**
 * Holds logic to update the FastEndpoint dependencies
 *
 * @author André Gil <andre_gil22@hotmail.com>
 *
 * @link https://github.com/matapatos/wp-fastendponts-depends
 */
class FastEndpointDependenciesGenerator
{
    /**
     * Holds all registered FastEndpoints router
     *
     * @var array<Router>
     */
    protected array $allRegisteredRouters = [];

    /**
     * Registers the logic to update the dependencies via the WP_CLI and
     * when the plugin is activated
     */
    public function register(string $filepath): void
    {
        // Update dependencies on plugin activation
        register_activation_hook($filepath, $this->update(...));
        if (defined('WP_CLI') && \WP_CLI) {
            WP_CLI::add_command('fast_endpoints_depends_update', $this->update(...));
        }
    }

    /**
     * Updates the dependencies option via hooks
     */
    public function update(): void
    {
        add_action('fastendpoints_after_register', $this->routerRegistered(...));
        add_filter('wp_loaded', $this->updateDependenciesOption(...));
    }

    /**
     * Called when a new FastEndpoints router is registered
     */
    public function routerRegistered($router): void
    {
        $this->allRegisteredRouters[] = $router;
        if (defined('WP_CLI') && \WP_CLI) {
            $numEndpoints = count($router->getEndpoints);
            WP_CLI::debug("Detected router with $numEndpoints endpoints");
        }
    }

    /**
     * Updates the fastendpoints_dependencies option that holds all the registered endpoints
     */
    public function updateDependenciesOption(): void
    {
        $routersDependencies = $this->getDependenciesFromRouters($this->allRegisteredRouters);
        if ($routersDependencies) {
            $activePlugins = wp_get_active_and_valid_plugins();
            foreach ($routersDependencies as $method => $methodDependencies) {
                foreach ($methodDependencies as $route => $dependencies) {
                    foreach ($dependencies as &$dependency) {
                        if (str_ends_with($dependency, '.php')) {
                            continue;
                        }

                        foreach ($activePlugins as $pluginFilepath) {
                            if (! str_contains($pluginFilepath, WP_PLUGIN_DIR.'/'.$dependency)) {
                                continue;
                            }

                            if (defined('WP_CLI') && \WP_CLI) {
                                WP_CLI::debug("[$method]$route updating dependency from $dependency to $pluginFilepath");
                            }
                            $dependency = $pluginFilepath;
                        }
                    }
                }
            }
        }
        update_option('fastendpoints_dependencies', $routersDependencies);
        if (defined('WP_CLI') && \WP_CLI) {
            WP_CLI\Utils\format_items('json', $routersDependencies);
            $numRouters = count($this->allRegisteredRouters);
            WP_CLI::success("Successfully updated dependencies for $numRouters routers");
        }
    }

    /**
     * Retrieves all endpoint dependencies from a set of routers
     *
     * @return array<string>
     */
    protected function getDependenciesFromRouters(array $allRouters): array
    {
        $dependencies = [];
        foreach ($allRouters as $router) {
            foreach ($router->getEndpoints() as $endpoint) {
                $allMethods = explode(',', $endpoint->getHttpMethod());
                foreach ($allMethods as $method) {
                    if (! isset($dependencies[$method])) {
                        $dependencies[$method] = [];
                    }
                    $route = $endpoint->getFullRestRoute();
                    $dependencies[$method][$route] = $endpoint->getRequiredPlugins();
                }
            }

            $allSubRouters = $router->getSubRouters();
            $dependencies = array_merge_recursive($dependencies, $this->getDependenciesFromRouters($allSubRouters));
        }

        return $dependencies;
    }
}

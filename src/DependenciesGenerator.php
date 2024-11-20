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
class DependenciesGenerator
{
    private ?string $configFilePath;

    private bool $isToUpdateOnPluginActivation;

    public function __construct(?string $configFilePath = null, bool $isToUpdateOnPluginActivation = true)
    {
        $this->configFilePath = $configFilePath;
        $this->isToUpdateOnPluginActivation = $isToUpdateOnPluginActivation;
    }

    /**
     * Registers the logic to update the dependencies via the WP_CLI and when a plugin is activated
     */
    public function register(): void
    {
        if ($this->isToUpdateOnPluginActivation) {
            add_action('activated_plugin', $this->update(...));
        }

        if ($this->isRunningCli()) {
            WP_CLI::add_command('fastendpoints', DependsCommand::class);
        }
    }

    /**
     * Updates the REST endpoint dependencies
     */
    public function update($plugin = null): void
    {
        if (did_action('wp_loaded')) {
            $this->updateDependenciesConfig();

            return;
        }
        add_action('wp_loaded', $this->updateDependenciesConfig(...));
    }

    /**
     * Retrieves the correspondent plugin full path from each dependency
     */
    protected function getDependenciesPluginFullPath(array $dependencies, string $httpMethod, string $route): array
    {
        $fullPathDependencies = [];
        $activePlugins = get_option('active_plugins', []);
        foreach ($dependencies as $dependency) {
            if (str_ends_with($dependency, '.php')) {
                $fullPathDependencies[] = $dependency;

                continue;
            }

            foreach ($activePlugins as $pluginFilepath) {
                if (! str_contains($pluginFilepath, $dependency)) {
                    continue;
                }

                if ($this->isRunningCli()) {
                    WP_CLI::debug("[$httpMethod] $route updating dependency from $dependency to $pluginFilepath");
                }
                $fullPathDependencies[] = $pluginFilepath;
                break;
            }
        }

        return $fullPathDependencies;
    }

    /**
     * Updates the dependencies config file
     */
    public function updateDependenciesConfig(): void
    {
        $this->markAsRunning();

        $autoloader = new DependsAutoloader;
        $autoloader->unregister();

        $dependencies = $this->getRoutesDependencies();
        $this->saveConfigFile($dependencies);

        if ($this->isRunningCli()) {
            WP_CLI::success('REST route dependencies updated');
        }
    }

    /**
     * Yields each route available in the REST server
     */
    protected function allRestRoutes(): iterable
    {
        $server = rest_get_server();
        $numRoutes = count($server->get_routes());
        $progressBar = $this->getProgressBar('Searching for route dependencies', $numRoutes);
        foreach ($server->get_namespaces() as $namespace) {
            $namespaceRoutes = $server->get_routes($namespace);
            foreach ($namespaceRoutes as $route => $allRoutes) {
                foreach ($allRoutes as $routeArgs) {
                    $httpMethods = $routeArgs['methods'] ?? [];
                    foreach ($httpMethods as $httpMethod => $enabled) {
                        if (! $enabled) {
                            continue;
                        }

                        yield [
                            'method' => $httpMethod,
                            'route' => $route,
                            'depends' => $routeArgs['depends'] ?? null,
                        ];
                    }
                    $progressBar->tick();
                }
            }
        }
        $progressBar->finish();
    }

    /**
     * Retrieves the dependencies/plugins required per each REST route
     *
     * @return array<string,array<string,string>>
     */
    protected function getRoutesDependencies(): array
    {
        $dependencies = [];
        foreach ($this->allRestRoutes() as $restRoute) {
            $httpMethod = $restRoute['method'];
            $route = $restRoute['route'];
            $routeDependencies = $restRoute['depends'];
            $routeDependencies = apply_filters('fastendpoints_depends_route', $routeDependencies, $httpMethod, $route);
            if ($routeDependencies === null) {
                continue;
            }
            if (! is_array($routeDependencies)) {
                if ($this->isRunningCli()) {
                    $varType = gettype($routeDependencies);
                    WP_CLI::warning("[{$httpMethod}] {$route} Invalid dependencies. Expecting either null or an array but {$varType} given");
                }

                continue;
            }

            if (! isset($dependencies[$httpMethod])) {
                $dependencies[$httpMethod] = [];
            }

            $routeDependencies = $this->getDependenciesPluginFullPath($routeDependencies, $httpMethod, $route);
            $dependencies[$httpMethod][$route] = $routeDependencies;
        }

        return $dependencies;
    }

    /**
     * Checks if it's running via CLI
     */
    protected function isRunningCli(): bool
    {
        return defined('WP_CLI') && \WP_CLI;
    }

    /**
     * Either retrieves a WP_CLI progress bar or a mock of it.
     * A mock is retrieved to avoid adding multiple if checks when interacting with it.
     *
     * @param  string  $message  - The progress bar message to be displayed
     * @param  int  $numItems  - The number of items to process
     */
    protected function getProgressBar(string $message, int $numItems)
    {
        if (! $this->isRunningCli()) {
            return new class
            {
                public function tick(): void {}

                public function finish(): void {}
            };
        }

        return WP_CLI\Utils\make_progress_bar($message, $numItems);
    }

    /**
     * Checks if it's running via CLI
     */
    protected function markAsRunning(): void
    {
        if (defined('FAST_ENDPOINTS_DEPENDS_UPDATE')) {
            return;
        }
        define('FAST_ENDPOINTS_DEPENDS_UPDATE', true);
    }

    /**
     * Updates the dependencies config file with the given route dependencies
     */
    protected function saveConfigFile(array $dependencies): void
    {
        $configFilePath = $this->getConfigFilePath();
        if (! $dependencies) {
            @unlink($configFilePath);

            return;
        }

        $timestamp = date('c');
        $data = "<?php # Generated ${timestamp}\r\n";
        $data .= 'return '.var_export($dependencies, true).';';
        file_put_contents($configFilePath, $data);
    }

    /**
     * Retrieves the file path which holds the REST dependencies
     */
    public function getConfigFilePath(): string
    {
        if ($this->configFilePath) {
            return $this->configFilePath;
        }

        if (defined('FASTENDPOINTS_DEPENDS_CONFIG_FILEPATH')) {
            return \FASTENDPOINTS_DEPENDS_CONFIG_FILEPATH;
        }

        return plugin_dir_path(__FILE__).'/../config.php';
    }
}

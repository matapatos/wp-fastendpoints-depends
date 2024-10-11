<?php

namespace Wp\FastEndpoints\Depends;

use WP_CLI;

/**
 * Manages REST endpoint dependencies
 *
 * @author André Gil <andre_gil22@hotmail.com>
 *
 * @link https://github.com/matapatos/wp-fastendponts-depends
 */
class DependsCommand
{
    private DependenciesGenerator $generator;

    public function __construct(?DependenciesGenerator $generator = null)
    {
        $this->generator = $generator ?: new DependenciesGenerator;
    }

    /**
     * Generates the REST dependencies needed for each endpoint
     */
    public function depends(): void
    {
        $this->generator->update();
    }

    /**
     * Clears the REST dependencies
     *
     * @subcommand depends-clear
     */
    public function _clear(): void
    {
        $configFilePath = $this->generator->getConfigFilePath();
        if (file_exists($configFilePath)) {
            unlink($configFilePath);
        }

        WP_CLI::success('REST route dependencies cleared');
    }
}

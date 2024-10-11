<?php

define('TESTS_DIR', __DIR__);
define('PLUGIN_ROOT_DIR', dirname(\TESTS_DIR));
define('SAMPLE_CONFIG_FILEPATH', \TESTS_DIR.'/Data/config.php');
define('EMPTY_CONFIG_FILEPATH', \TESTS_DIR.'/Data/empty-config.php');
define('ROUTERS_DIR', \TESTS_DIR.'/Integration/Routers/');
if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/wp/plugins');
}

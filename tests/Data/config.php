<?php # Generated 2024-11-19T16:47:06+00:00
return array (
    'POST' =>
        array(
            '/my-plugin/v1/blog-post' =>
                array (
                    0 => 'my-plugin/my-plugin.php',
                ),
            '/my-plugin/v1/no-depends' => array(),
        ),
    'GET' =>
        array (
            '/my-plugin/v1/user' =>
                array (
                    0 => 'my-plugin/my-plugin.php',
                    1 => 'buddypress/buddypress.php',
                ),
        ),
    'DELETE' =>
        array (
            '/my-plugin/v1/blog-post/(?P<ID>[\\d]+)' =>
                array (
                    0 => 'my-plugin/my-plugin.php',
                ),
        ),
);

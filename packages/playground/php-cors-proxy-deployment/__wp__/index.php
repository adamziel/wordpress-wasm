<?php

if (
        empty( $_SERVER['PATH_INFO'] ) &&           
        !str_starts_with($_SERVER['REQUEST_URI'], '/?')
) {                                                 
        // Allow proxied URL to be provided via request URI,
        // even though WP cloud servers don't provided PATH_INFO.
        $_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'];
}
require __DIR__ . '/../cors-proxy.php';

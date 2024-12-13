<?php

/**
 * Box, very annoyingly, force-adds a platform_check.php file
 * into the final built .phar archive. The vendor libraries
 * do work with a PHP version lower than 8.1 enforced by that
 * platform_check.php file, so let's just truncate it.
 */

$file = $argv[1];
$phar = new Phar($file);
$phar->startBuffering();
$phar['vendor/composer/platform_check.php'] = ''; // Set to empty string to truncate
$phar->stopBuffering();


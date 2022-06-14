<?php

fwrite(STDERR, 'Bootstrapping Sidecar PHP runtime.' . PHP_EOL);

ini_set('display_errors', '1');

error_reporting(E_ALL);

if (! file_exists('/tmp/opcache')) {
    mkdir('/tmp/opcache');
}

require __DIR__ . '/vendor/autoload.php';

fwrite(STDERR, 'Composer autoload file loaded.' . PHP_EOL);

return require __DIR__ . '/loop.php';

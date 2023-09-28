<?php

spl_autoload_register(function (string $class): void {
    error_log($class);
    if (strncmp($class, 'PKP\\dev\\fixers\\', 15) !== 0) {
        return;
    }

    require __DIR__ . '/' . str_replace('\\', '/', substr($class, 15)) . '.php';
});

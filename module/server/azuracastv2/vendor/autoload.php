<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'AzuraCastV2\\';
    $baseDir = __DIR__ . '/../includes/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

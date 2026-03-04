<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'AzuraCastV2\\';
    $baseDir = __DIR__ . '/../includes/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass) . '.php';

    $candidates = [
        $baseDir . $relativePath,
        $baseDir . preg_replace('#^Api/#', 'api/', $relativePath),
        $baseDir . str_replace('/Api/', '/api/', $relativePath),
    ];

    foreach ($candidates as $file) {
        if (is_string($file) && file_exists($file)) {
            require $file;
            return;
        }
    }
});

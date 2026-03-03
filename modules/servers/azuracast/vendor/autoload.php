<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'WHMCS\\Module\\Server\\AzuraCast\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relative) . '.php';
    $file = __DIR__ . '/../lib/' . $relativePath;

    if (is_file($file)) {
        require_once $file;
    }
});

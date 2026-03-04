<?php

declare(strict_types=1);

namespace AzuraCastV2\Classes;

use WHMCS\Database\Capsule;

class LogManager
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $line = sprintf("[%s] [%s] %s", date('Y-m-d H:i:s'), $level, $message);

        $existing = file_exists($this->logFile) ? file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        array_unshift($existing, $line);
        $existing = array_slice($existing, 0, 30);

        file_put_contents($this->logFile, implode(PHP_EOL, $existing) . PHP_EOL);

        if (class_exists(Capsule::class)) {
            try {
                Capsule::table('azuracastv2_logs')->insert([
                    'level' => $level,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $count = Capsule::table('azuracastv2_logs')->count();
                if ($count > 30) {
                    $deleteIds = Capsule::table('azuracastv2_logs')
                        ->orderBy('id', 'asc')
                        ->limit($count - 30)
                        ->pluck('id')
                        ->toArray();
                    if (!empty($deleteIds)) {
                        Capsule::table('azuracastv2_logs')->whereIn('id', $deleteIds)->delete();
                    }
                }
            } catch (\Throwable $exception) {
                // Sem ação: evita loop de log em caso de falha de banco.
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace AzuraCastV2\Api;

class StringHelper
{
    public static function normalizeBaseUrl(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (!str_starts_with($trimmed, 'https://')) {
            $trimmed = 'https://' . preg_replace('#^https?://#', '', $trimmed);
        }

        return rtrim($trimmed, '/');
    }
}

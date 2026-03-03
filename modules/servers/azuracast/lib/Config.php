<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\AzuraCast;

final class Config
{
    public static function metadata(): array
    {
        return [
            'DisplayName' => 'AzuraCast V2',
            'APIVersion' => '1.1',
            'RequiresServer' => true,
            'DefaultNonSSLPort' => '80',
            'DefaultSSLPort' => '443',
        ];
    }

    public static function options(): array
    {
        // Produto sem opções técnicas: usa configuração do servidor WHMCS.
        return [];
    }
}

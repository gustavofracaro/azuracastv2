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
        return [
            'API Base URL' => [
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'Opcional. Se vazio usa Host/IP do servidor WHMCS.',
            ],
            'API Key' => [
                'Type' => 'password',
                'Size' => '128',
                'Description' => 'Opcional. Se vazio usa Access Hash do servidor.',
            ],
            'Verify SSL' => ['Type' => 'yesno', 'Description' => 'Validar certificado SSL'],
            'HTTP Timeout' => ['Type' => 'text', 'Size' => '4', 'Default' => '60'],

            'Create Endpoint' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/stations'],
            'Update Endpoint' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}'],
            'Terminate Endpoint' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}'],
            'Suspend Endpoint' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}'],
            'Unsuspend Endpoint' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}'],

            'Station Name Prefix' => ['Type' => 'text', 'Size' => '30', 'Default' => 'radio-'],
            'Default Timezone' => ['Type' => 'text', 'Size' => '40', 'Default' => 'America/Sao_Paulo'],
            'Default Language' => ['Type' => 'text', 'Size' => '20', 'Default' => 'pt_BR'],
            'Default Frontend Type' => ['Type' => 'dropdown', 'Options' => 'icecast,shoutcast2,remote', 'Default' => 'icecast'],
            'Default Backend Type' => ['Type' => 'dropdown', 'Options' => 'liquidsoap,none,remote', 'Default' => 'liquidsoap'],
            'Default Frontend Port' => ['Type' => 'text', 'Size' => '5', 'Default' => '8000'],
            'Default Backend Port' => ['Type' => 'text', 'Size' => '5', 'Default' => '8005'],
            'Default Max Listeners' => ['Type' => 'text', 'Size' => '6', 'Default' => '0'],

            'Create Payload JSON' => ['Type' => 'textarea', 'Rows' => '8', 'Cols' => '80'],
            'Suspend Payload JSON' => ['Type' => 'textarea', 'Rows' => '4', 'Cols' => '80', 'Default' => '{"is_enabled":false}'],
            'Unsuspend Payload JSON' => ['Type' => 'textarea', 'Rows' => '4', 'Cols' => '80', 'Default' => '{"is_enabled":true}'],
            'Change Package Payload JSON' => ['Type' => 'textarea', 'Rows' => '6', 'Cols' => '80'],
            'Follow Redirects' => ['Type' => 'yesno'],
            'Max Redirects' => ['Type' => 'text', 'Size' => '3', 'Default' => '5'],
        ];
    }
}

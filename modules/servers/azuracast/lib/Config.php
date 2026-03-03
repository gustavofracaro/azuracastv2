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
            'Verificar SSL' => ['Type' => 'yesno', 'Description' => 'Validar certificado SSL nas chamadas da API'],
            'Timeout HTTP' => ['Type' => 'text', 'Size' => '4', 'Default' => '60', 'Description' => 'Tempo limite total da requisição em segundos'],

            'Endpoint Criar' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/stations', 'Description' => 'Endpoint para criação de estação'],
            'Endpoint Atualizar' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}', 'Description' => 'Endpoint para atualização de estação'],
            'Endpoint Excluir' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}', 'Description' => 'Endpoint para remoção da estação'],
            'Endpoint Suspender' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}', 'Description' => 'Endpoint para suspensão lógica'],
            'Endpoint Reativar' => ['Type' => 'text', 'Size' => '64', 'Default' => '/api/admin/station/{station_id}', 'Description' => 'Endpoint para reativação lógica'],

            'Prefixo Nome Curto' => ['Type' => 'text', 'Size' => '30', 'Default' => 'radio-', 'Description' => 'Prefixo para montar o short_name da estação'],
            'Timezone Padrão' => ['Type' => 'text', 'Size' => '40', 'Default' => 'America/Sao_Paulo', 'Description' => 'Fuso horário padrão da estação'],
            'Idioma Padrão' => ['Type' => 'text', 'Size' => '20', 'Default' => 'pt_BR', 'Description' => 'Idioma padrão da estação'],
            'Frontend Padrão' => ['Type' => 'dropdown', 'Options' => 'icecast,shoutcast2,remote', 'Default' => 'icecast', 'Description' => 'Frontend padrão do streaming'],
            'Backend Padrão' => ['Type' => 'dropdown', 'Options' => 'liquidsoap,none,remote', 'Default' => 'liquidsoap', 'Description' => 'Backend padrão do streaming'],
            'Porta Frontend' => ['Type' => 'text', 'Size' => '5', 'Default' => '8000', 'Description' => 'Porta padrão de entrada de ouvintes'],
            'Porta Backend' => ['Type' => 'text', 'Size' => '5', 'Default' => '8005', 'Description' => 'Porta padrão do backend'],
            'Limite Ouvintes' => ['Type' => 'text', 'Size' => '6', 'Default' => '0', 'Description' => '0 para ilimitado'],

            'Payload Criar (JSON)' => ['Type' => 'textarea', 'Rows' => '8', 'Cols' => '80', 'Description' => 'JSON opcional para mesclar no payload de criação'],
            'Payload Suspender (JSON)' => ['Type' => 'textarea', 'Rows' => '4', 'Cols' => '80', 'Default' => '{"is_enabled":false}', 'Description' => 'JSON enviado ao suspender'],
            'Payload Reativar (JSON)' => ['Type' => 'textarea', 'Rows' => '4', 'Cols' => '80', 'Default' => '{"is_enabled":true}', 'Description' => 'JSON enviado ao reativar'],
            'Payload Alterar Plano (JSON)' => ['Type' => 'textarea', 'Rows' => '6', 'Cols' => '80', 'Description' => 'JSON opcional para upgrade/downgrade'],

            'Seguir Redirecionamentos' => ['Type' => 'yesno', 'Default' => 'on', 'Description' => 'Seguir redirecionamentos 301/302/307/308 automaticamente'],
            'Máx. Redirecionamentos' => ['Type' => 'text', 'Size' => '3', 'Default' => '5', 'Description' => 'Quantidade máxima de redirecionamentos'],
        ];
    }
}

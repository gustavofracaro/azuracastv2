<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function azuracast_MetaData(): array
{
    return [
        'DisplayName' => 'AzuraCast Streaming Automation',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
    ];
}

function azuracast_ConfigOptions(): array
{
    return [
        'API Base URL' => [
            'Type' => 'text',
            'Size' => '64',
            'Default' => 'https://radio.example.com',
            'Description' => 'URL do painel AzuraCast (sem barra final)',
        ],
        'API Key' => [
            'Type' => 'password',
            'Size' => '128',
            'Description' => 'Chave da API (Admin > API Keys)',
        ],
        'Verify SSL' => [
            'Type' => 'yesno',
            'Description' => 'Marque para validar certificado SSL',
        ],
        'HTTP Timeout' => [
            'Type' => 'text',
            'Size' => '4',
            'Default' => '60',
            'Description' => 'Tempo em segundos para timeout HTTP',
        ],

        'Create Endpoint' => [
            'Type' => 'text',
            'Size' => '64',
            'Default' => '/api/admin/stations',
            'Description' => 'POST para criação de estação',
        ],
        'Update Endpoint' => [
            'Type' => 'text',
            'Size' => '64',
            'Default' => '/api/admin/station/{station_id}',
            'Description' => 'PUT para atualização de estação',
        ],
        'Terminate Endpoint' => [
            'Type' => 'text',
            'Size' => '64',
            'Default' => '/api/admin/station/{station_id}',
            'Description' => 'DELETE para remoção da estação',
        ],
        'Suspend Endpoint' => [
            'Type' => 'text',
            'Size' => '64',
            'Default' => '/api/admin/station/{station_id}',
            'Description' => 'Endpoint para suspensão lógica',
        ],
        'Unsuspend Endpoint' => [
            'Type' => 'text',
            'Size' => '64',
            'Default' => '/api/admin/station/{station_id}',
            'Description' => 'Endpoint para reativação lógica',
        ],

        'Station Name Prefix' => [
            'Type' => 'text',
            'Size' => '30',
            'Default' => 'radio-',
        ],
        'Default Timezone' => [
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'America/Sao_Paulo',
        ],
        'Default Language' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => 'pt_BR',
        ],
        'Default Frontend Type' => [
            'Type' => 'dropdown',
            'Options' => 'icecast,shoutcast2,remote',
            'Default' => 'icecast',
        ],
        'Default Backend Type' => [
            'Type' => 'dropdown',
            'Options' => 'liquidsoap,none,remote',
            'Default' => 'liquidsoap',
        ],
        'Default Frontend Port' => [
            'Type' => 'text',
            'Size' => '5',
            'Default' => '8000',
        ],
        'Default Backend Port' => [
            'Type' => 'text',
            'Size' => '5',
            'Default' => '8005',
        ],
        'Default Max Listeners' => [
            'Type' => 'text',
            'Size' => '6',
            'Default' => '0',
            'Description' => '0 = ilimitado',
        ],

        'Create Payload JSON' => [
            'Type' => 'textarea',
            'Rows' => '8',
            'Cols' => '80',
            'Description' => 'JSON opcional para merge no payload de criação (aceita placeholders: {{service_id}}, {{domain}}, {{username}}, {{station_short_name}}).',
        ],
        'Suspend Payload JSON' => [
            'Type' => 'textarea',
            'Rows' => '4',
            'Cols' => '80',
            'Default' => '{"is_enabled":false}',
            'Description' => 'Payload JSON para suspensão',
        ],
        'Unsuspend Payload JSON' => [
            'Type' => 'textarea',
            'Rows' => '4',
            'Cols' => '80',
            'Default' => '{"is_enabled":true}',
            'Description' => 'Payload JSON para reativação',
        ],
        'Change Package Payload JSON' => [
            'Type' => 'textarea',
            'Rows' => '6',
            'Cols' => '80',
            'Description' => 'Payload JSON para upgrade/downgrade (merge com campos padrão)',
        ],
    ];
}

function azuracast_CreateAccount(array $params)
{
    try {
        $existingStationId = azuracast_getStationId($params);
        if (!empty($existingStationId)) {
            return 'Serviço já possui station_id salvo: ' . $existingStationId;
        }

        $payload = azuracast_buildBaseStationPayload($params);
        $payload = azuracast_mergeTemplateJson($payload, (string) ($params['configoption18'] ?? ''), $params);
        $payload = azuracast_mergeWhmcsCustomFields($payload, $params);

        $result = azuracast_apiRequest($params, 'POST', (string) $params['configoption5'], $payload);
        azuracast_ensureSuccess($result, 'Falha ao criar estação.');

        $decoded = json_decode($result['body'], true);
        if (!is_array($decoded)) {
            return 'Estação criada, mas a resposta da API não é JSON válido.';
        }

        $stationId = $decoded['id'] ?? ($decoded['station']['id'] ?? null);
        if (!$stationId) {
            return 'Conta criada, mas o ID da estação não foi retornado pela API.';
        }

        azuracast_saveStationId($params, (string) $stationId);

        return 'success';
    } catch (Throwable $e) {
        return 'Erro ao provisionar: ' . $e->getMessage();
    }
}

function azuracast_SuspendAccount(array $params)
{
    return azuracast_stationPayloadAction(
        $params,
        'PUT',
        (string) $params['configoption8'],
        (string) ($params['configoption19'] ?? '{"is_enabled":false}')
    );
}

function azuracast_UnsuspendAccount(array $params)
{
    return azuracast_stationPayloadAction(
        $params,
        'PUT',
        (string) $params['configoption9'],
        (string) ($params['configoption20'] ?? '{"is_enabled":true}')
    );
}

function azuracast_TerminateAccount(array $params)
{
    try {
        $stationId = azuracast_requireStationId($params);
        $endpoint = azuracast_resolveStationEndpoint((string) $params['configoption7'], $stationId);

        $result = azuracast_apiRequest($params, 'DELETE', $endpoint);
        azuracast_ensureSuccess($result, 'Falha ao remover estação.');

        azuracast_saveStationId($params, '');

        return 'success';
    } catch (Throwable $e) {
        return 'Erro no cancelamento: ' . $e->getMessage();
    }
}

function azuracast_ChangePackage(array $params)
{
    try {
        $stationId = azuracast_requireStationId($params);
        $endpoint = azuracast_resolveStationEndpoint((string) $params['configoption6'], $stationId);

        $payload = [
            'frontend_config' => [
                'port' => (int) ($params['configoption15'] ?? 8000),
                'max_listeners' => (int) ($params['configoption17'] ?? 0),
            ],
            'backend_config' => [
                'port' => (int) ($params['configoption16'] ?? 8005),
            ],
        ];

        $payload = azuracast_mergeTemplateJson($payload, (string) ($params['configoption21'] ?? ''), $params);
        $payload = azuracast_mergeWhmcsCustomFields($payload, $params);

        $result = azuracast_apiRequest($params, 'PUT', $endpoint, $payload);
        azuracast_ensureSuccess($result, 'Falha ao atualizar pacote da estação.');

        return 'success';
    } catch (Throwable $e) {
        return 'Erro ao alterar pacote: ' . $e->getMessage();
    }
}

function azuracast_TestConnection(array $params): array
{
    try {
        $result = azuracast_apiRequest($params, 'GET', '/api/status');
        if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => 'HTTP ' . $result['http_code'] . ' - ' . $result['body']];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function azuracast_ClientAreaCustomButtonArray(): array
{
    return [
        'Abrir Painel da Estação' => 'openPanel',
        'Sincronizar station_id pela API' => 'syncStationByShortName',
    ];
}

function azuracast_AdminCustomButtonArray(): array
{
    return [
        'Abrir Painel da Estação' => 'openPanel',
        'Sincronizar station_id pela API' => 'syncStationByShortName',
    ];
}

function azuracast_openPanel(array $params)
{
    try {
        $stationId = azuracast_requireStationId($params);
        $base = rtrim((string) $params['configoption1'], '/');

        return [
            'success' => true,
            'redirectTo' => $base . '/station/' . rawurlencode((string) $stationId),
        ];
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function azuracast_syncStationByShortName(array $params)
{
    try {
        $shortName = azuracast_buildShortName($params);
        $result = azuracast_apiRequest($params, 'GET', '/api/admin/stations');
        azuracast_ensureSuccess($result, 'Falha ao listar estações para sincronização.');

        $stations = json_decode($result['body'], true);
        if (!is_array($stations)) {
            return 'Resposta inesperada ao listar estações.';
        }

        foreach ($stations as $station) {
            if (!is_array($station)) {
                continue;
            }

            if (($station['short_name'] ?? null) === $shortName && !empty($station['id'])) {
                azuracast_saveStationId($params, (string) $station['id']);
                return 'success';
            }
        }

        return 'Estação não encontrada para short_name: ' . $shortName;
    } catch (Throwable $e) {
        return 'Erro na sincronização: ' . $e->getMessage();
    }
}

function azuracast_stationPayloadAction(array $params, string $method, string $endpointTemplate, string $payloadJson)
{
    try {
        $stationId = azuracast_requireStationId($params);
        $endpoint = azuracast_resolveStationEndpoint($endpointTemplate, $stationId);
        $payload = azuracast_parseJsonTemplate($payloadJson, $params);

        $result = azuracast_apiRequest($params, $method, $endpoint, $payload);
        azuracast_ensureSuccess($result, 'Falha na ação da estação.');

        return 'success';
    } catch (Throwable $e) {
        return 'Erro na ação da estação: ' . $e->getMessage();
    }
}

function azuracast_buildBaseStationPayload(array $params): array
{
    $payload = [
        'name' => azuracast_stationDisplayName($params),
        'description' => 'Provisionado via WHMCS. Service ID: ' . ($params['serviceid'] ?? ''),
        'short_name' => azuracast_buildShortName($params),
        'frontend_type' => (string) ($params['configoption13'] ?? 'icecast'),
        'backend_type' => (string) ($params['configoption14'] ?? 'liquidsoap'),
        'frontend_config' => [
            'port' => (int) ($params['configoption15'] ?? 8000),
            'max_listeners' => (int) ($params['configoption17'] ?? 0),
        ],
        'backend_config' => [
            'port' => (int) ($params['configoption16'] ?? 8005),
        ],
        'timezone' => (string) ($params['configoption11'] ?? 'UTC'),
        'default_language' => (string) ($params['configoption12'] ?? 'en_US'),
        'enable_public_page' => true,
        'enable_streamers' => true,
    ];

    return $payload;
}

function azuracast_mergeWhmcsCustomFields(array $payload, array $params): array
{
    $customFields = $params['customfields'] ?? [];
    if (!is_array($customFields)) {
        return $payload;
    }

    $knownScalarFields = [
        'name',
        'description',
        'timezone',
        'default_language',
        'frontend_type',
        'backend_type',
        'max_bitrate',
        'is_public',
        'enable_public_page',
        'enable_on_demand',
        'enable_streamers',
        'record_streams',
    ];

    foreach ($knownScalarFields as $field) {
        $key = 'azuracast_' . $field;
        if (array_key_exists($key, $customFields) && $customFields[$key] !== '') {
            $payload[$field] = azuracast_castValue($customFields[$key]);
        }
    }

    $jsonOverrideKey = 'azuracast_payload_json';
    if (!empty($customFields[$jsonOverrideKey])) {
        $payload = azuracast_mergeTemplateJson($payload, (string) $customFields[$jsonOverrideKey], $params);
    }

    if (!empty($customFields['station_name'])) {
        $payload['name'] = trim((string) $customFields['station_name']);
    }

    return $payload;
}

function azuracast_mergeTemplateJson(array $basePayload, string $json, array $params): array
{
    if (trim($json) === '') {
        return $basePayload;
    }

    $extra = azuracast_parseJsonTemplate($json, $params);
    return azuracast_arrayMergeRecursiveDistinct($basePayload, $extra);
}

function azuracast_parseJsonTemplate(string $json, array $params): array
{
    if (trim($json) === '') {
        return [];
    }

    $shortName = azuracast_buildShortName($params);
    $replaced = strtr($json, [
        '{{service_id}}' => (string) ($params['serviceid'] ?? ''),
        '{{domain}}' => (string) ($params['domain'] ?? ''),
        '{{username}}' => (string) ($params['username'] ?? ''),
        '{{station_short_name}}' => $shortName,
    ]);

    $decoded = json_decode($replaced, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON inválido em payload template.');
    }

    return $decoded;
}

function azuracast_arrayMergeRecursiveDistinct(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = azuracast_arrayMergeRecursiveDistinct($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function azuracast_apiRequest(array $params, string $method, string $endpoint, ?array $payload = null): array
{
    $baseUrl = rtrim((string) ($params['configoption1'] ?? ''), '/');
    $apiKey = trim((string) ($params['configoption2'] ?? ''));
    $verifySsl = !empty($params['configoption3']);
    $timeout = (int) ($params['configoption4'] ?? 60);

    if ($baseUrl === '' || $apiKey === '') {
        throw new RuntimeException('Configurações obrigatórias ausentes: API Base URL e API Key.');
    }

    $url = $baseUrl . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Não foi possível iniciar CURL.');
    }

    $headers = [
        'Accept: application/json',
        'X-API-Key: ' . $apiKey,
    ];

    $body = null;
    if ($payload !== null) {
        $body = json_encode($payload);
        if ($body === false) {
            throw new RuntimeException('Falha ao serializar payload em JSON.');
        }

        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(5, $timeout),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logModuleCall(
        'azuracast',
        strtoupper($method) . ' ' . $endpoint,
        $payload,
        [
            'http_code' => $httpCode,
            'response' => $responseBody,
            'curl_error' => $curlError,
        ],
        $body
    );

    if ($responseBody === false) {
        throw new RuntimeException('Erro CURL: ' . $curlError);
    }

    return [
        'http_code' => $httpCode,
        'body' => (string) $responseBody,
    ];
}

function azuracast_ensureSuccess(array $result, string $message): void
{
    if (($result['http_code'] ?? 0) >= 300) {
        throw new RuntimeException($message . ' HTTP ' . $result['http_code'] . ': ' . ($result['body'] ?? ''));
    }
}

function azuracast_buildShortName(array $params): string
{
    $prefix = trim((string) ($params['configoption10'] ?? 'radio-'));
    $candidate = $prefix . ($params['serviceid'] ?? '');

    $customFields = $params['customfields'] ?? [];
    if (!empty($customFields['azuracast_short_name'])) {
        $candidate = (string) $customFields['azuracast_short_name'];
    }

    $clean = preg_replace('/[^a-z0-9_\-]/', '', strtolower($candidate));
    if (!$clean) {
        $clean = 'radio' . ($params['serviceid'] ?? '');
    }

    return $clean;
}

function azuracast_stationDisplayName(array $params): string
{
    $domain = trim((string) ($params['domain'] ?? ''));
    if ($domain !== '') {
        return 'Rádio ' . $domain;
    }

    return 'Rádio #' . ($params['serviceid'] ?? '');
}

function azuracast_requireStationId(array $params): string
{
    $stationId = azuracast_getStationId($params);
    if (!$stationId) {
        throw new RuntimeException('station_id não encontrado para este serviço.');
    }

    return $stationId;
}

function azuracast_getStationId(array $params): ?string
{
    $customFields = $params['customfields'] ?? [];
    if (!is_array($customFields)) {
        $customFields = [];
    }

    foreach (['station_id', 'Station ID', 'stationid', 'azuracast_station_id'] as $key) {
        if (!empty($customFields[$key])) {
            return trim((string) $customFields[$key]);
        }
    }

    return null;
}

function azuracast_resolveStationEndpoint(string $template, string $stationId): string
{
    if (trim($template) === '') {
        throw new RuntimeException('Endpoint da ação não configurado.');
    }

    return str_replace('{station_id}', rawurlencode($stationId), $template);
}

function azuracast_castValue($value)
{
    $value = trim((string) $value);
    $lower = strtolower($value);

    if ($lower === 'true' || $lower === 'yes' || $lower === 'on') {
        return true;
    }

    if ($lower === 'false' || $lower === 'no' || $lower === 'off') {
        return false;
    }

    if (is_numeric($value)) {
        return strpos($value, '.') !== false ? (float) $value : (int) $value;
    }

    return $value;
}

function azuracast_saveStationId(array $params, string $stationId): void
{
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $productId = (int) ($params['pid'] ?? 0);
    if ($serviceId <= 0 || $productId <= 0) {
        return;
    }

    $customField = Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('relid', $productId)
        ->whereIn('fieldname', ['station_id', 'Station ID', 'stationid', 'azuracast_station_id'])
        ->orderBy('id', 'asc')
        ->first();

    if (!$customField) {
        return;
    }

    $exists = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $customField->id)
        ->where('relid', $serviceId)
        ->first();

    if ($exists) {
        Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $customField->id)
            ->where('relid', $serviceId)
            ->update(['value' => $stationId]);

        return;
    }

    Capsule::table('tblcustomfieldsvalues')->insert([
        'fieldid' => $customField->id,
        'relid' => $serviceId,
        'value' => $stationId,
    ]);
}

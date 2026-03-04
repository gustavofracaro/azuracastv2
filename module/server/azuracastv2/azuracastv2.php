<?php

declare(strict_types=1);

use AzuraCastV2\Api\AzuraCastApiClient;
use AzuraCastV2\Api\StringHelper;
use AzuraCastV2\Classes\Installer;
use AzuraCastV2\Classes\LogManager;
use AzuraCastV2\Classes\StationRepository;
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Este arquivo não pode ser acessado diretamente.');
}

require_once __DIR__ . '/vendor/autoload.php';

function azuracastv2_MetaData(): array
{
    return [
        'DisplayName' => 'Azuracast V2',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
    ];
}

function azuracastv2_ConfigOptions(): array
{
    return [
        'Nome da Estação' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Slug/nome da estação a ser criada no AzuraCast.',
        ],
        'Descrição' => [
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Descrição da estação.',
        ],
        'Máximo de Ouvintes' => [
            'Type' => 'text',
            'Default' => '100',
            'Description' => 'Limite de ouvintes simultâneos.',
        ],
        'Espaço em Disco (MB)' => [
            'Type' => 'text',
            'Default' => '1024',
            'Description' => 'Armazenamento máximo de mídia em MB.',
        ],
        'Bitrate Padrão' => [
            'Type' => 'dropdown',
            'Options' => '64,96,128,192,256,320',
            'Default' => '128',
            'Description' => 'Bitrate padrão do stream em kbps.',
        ],
        'AutoDJ Ativado' => [
            'Type' => 'yesno',
            'Description' => 'Criar estação com AutoDJ ativo.',
        ],
    ];
}

function azuracastv2_TestConnection(array $params): array
{
    $log = new LogManager(__DIR__ . '/registro.log');

    try {
        Installer::ensureTables();
        $client = buildClient($params);
        $response = $client->testConnection();

        if (!($response['ok'] ?? false)) {
            $message = (string) ($response['message'] ?? 'Falha desconhecida de conexão.');
            $log->error('Teste de conexão falhou: ' . $message);

            return [
                'success' => false,
                'error' => 'Falha na conexão HTTPS com API: ' . $message,
            ];
        }

        $log->info('Teste de conexão realizado com sucesso.');

        return [
            'success' => true,
            'error' => '',
        ];
    } catch (Throwable $exception) {
        $log->error('Exceção em TestConnection: ' . $exception->getMessage());

        return [
            'success' => false,
            'error' => 'Erro ao validar conexão: ' . $exception->getMessage(),
        ];
    }
}

function azuracastv2_CreateAccount(array $params): string
{
    return handleProvisionAction($params, static function (AzuraCastApiClient $client, StationRepository $repository, array $stationData): void {
        $station = $client->createStation($stationData);
        $repository->saveStation((int) $station['id'], (string) $station['name'], 'active', $station);
    }, 'CreateAccount');
}

function azuracastv2_SuspendAccount(array $params): string
{
    return handleProvisionAction($params, static function (AzuraCastApiClient $client, StationRepository $repository): void {
        $station = $repository->findStationByServiceId();
        $client->updateStation((int) $station['azuracast_station_id'], ['is_enabled' => false]);
        $repository->updateStatus((int) $station['azuracast_station_id'], 'suspended');
    }, 'SuspendAccount');
}

function azuracastv2_UnsuspendAccount(array $params): string
{
    return handleProvisionAction($params, static function (AzuraCastApiClient $client, StationRepository $repository): void {
        $station = $repository->findStationByServiceId();
        $client->updateStation((int) $station['azuracast_station_id'], ['is_enabled' => true]);
        $repository->updateStatus((int) $station['azuracast_station_id'], 'active');
    }, 'UnsuspendAccount');
}

function azuracastv2_TerminateAccount(array $params): string
{
    return handleProvisionAction($params, static function (AzuraCastApiClient $client, StationRepository $repository): void {
        $station = $repository->findStationByServiceId();
        $client->deleteStation((int) $station['azuracast_station_id']);
        $repository->deleteByServiceId();
    }, 'TerminateAccount');
}

function azuracastv2_ChangePackage(array $params): string
{
    return handleProvisionAction($params, static function (AzuraCastApiClient $client, StationRepository $repository, array $stationData): void {
        $station = $repository->findStationByServiceId();
        $client->updateStation((int) $station['azuracast_station_id'], $stationData);
        $repository->updateSnapshot((int) $station['azuracast_station_id'], $stationData);
    }, 'ChangePackage');
}

function azuracastv2_ClientArea(array $params): array
{
    $log = new LogManager(__DIR__ . '/registro.log');

    try {
        Installer::ensureTables();
        $client = buildClient($params);
        $repository = new StationRepository($params);
        $station = $repository->findStationByServiceId(false);

        $stationId = $station ? (int) $station['azuracast_station_id'] : 0;

        $stats = $stationId > 0 ? $client->getStationOverview($stationId) : [];
        $playlists = $stationId > 0 ? $client->getStationPlaylists($stationId) : [];

        return [
            'templatefile' => 'template/clientarea',
            'vars' => [
                'moduleLink' => $params['modulelink'],
                'serviceId' => $params['serviceid'],
                'stationId' => $stationId,
                'stationName' => $stats['name'] ?? ($params['configoption1'] ?? '-'),
                'publicPage' => $stats['public_page_url'] ?? '#',
                'adminPanel' => $stats['backend_url'] ?? '#',
                'listeners' => $stats['listeners']['current'] ?? 0,
                'listenersUnique' => $stats['listeners']['unique'] ?? 0,
                'bitrate' => $stats['listeners']['total_bitrate'] ?? 0,
                'nowPlaying' => $stats['now_playing']['song']['title'] ?? 'Sem dados',
                'nowArtist' => $stats['now_playing']['song']['artist'] ?? 'Sem dados',
                'streamUrl' => $stats['listen_url'] ?? '',
                'playlists' => $playlists,
                'status' => $stats['is_online'] ?? false,
            ],
        ];
    } catch (Throwable $exception) {
        $log->error('Falha no ClientArea: ' . $exception->getMessage());

        return [
            'templatefile' => 'template/clientarea',
            'vars' => [
                'errorMessage' => 'Não foi possível carregar os dados da rádio no momento.',
                'moduleLink' => $params['modulelink'],
                'playlists' => [],
            ],
        ];
    }
}

function azuracastv2_ClientAreaCustomButtonArray(array $params): array
{
    return [
        'Sincronizar Dados da Rádio' => 'syncStation',
    ];
}

function azuracastv2_syncStation(array $params): string
{
    return handleProvisionAction($params, static function (AzuraCastApiClient $client, StationRepository $repository): void {
        $station = $repository->findStationByServiceId();
        $snapshot = $client->getStationOverview((int) $station['azuracast_station_id']);
        $repository->updateSnapshot((int) $station['azuracast_station_id'], $snapshot);
    }, 'SyncStation');
}

function handleProvisionAction(array $params, callable $callback, string $operation): string
{
    $log = new LogManager(__DIR__ . '/registro.log');

    try {
        Installer::ensureTables();
        $client = buildClient($params);
        $repository = new StationRepository($params);
        $stationData = mapStationPayload($params);

        $callback($client, $repository, $stationData);
        $log->info($operation . ' executado com sucesso para serviceid=' . ($params['serviceid'] ?? '0'));

        return 'success';
    } catch (Throwable $exception) {
        $log->error($operation . ' falhou: ' . $exception->getMessage());

        return 'Falha no processo ' . $operation . ': ' . $exception->getMessage();
    }
}

function buildClient(array $params): AzuraCastApiClient
{
    $host = StringHelper::normalizeBaseUrl(getServerHost($params));
    $token = getServerApiToken($params);

    if ($host === '' || $token === '') {
        throw new RuntimeException('Hostname/IP e Token API Key são obrigatórios. Dados resolvidos: host=' . ($host !== '' ? 'ok' : 'vazio') . ', token=' . ($token !== '' ? 'ok' : 'vazio'));
    }

    return new AzuraCastApiClient($host, $token, new LogManager(__DIR__ . '/registro.log'));
}

function mapStationPayload(array $params): array
{
    $stationName = (string) ($params['configoption1'] ?? '');
    if ($stationName === '') {
        $stationName = getCustomFieldValue($params, ['Nome da Estação', 'nome_da_estacao', 'station_name']);
    }

    $description = (string) ($params['configoption2'] ?? '');
    if ($description === '') {
        $description = getCustomFieldValue($params, ['Descrição', 'descricao', 'description']);
    }

    return [
        'name' => $stationName !== '' ? $stationName : 'radio-' . ($params['serviceid'] ?? '0'),
        'description' => $description,
        'max_listeners' => (int) ($params['configoption3'] ?? 100),
        'media_storage_quota' => (int) ($params['configoption4'] ?? 1024),
        'default_bitrate' => (int) ($params['configoption5'] ?? 128),
        'enable_autodj' => !empty($params['configoption6']),
    ];
}

function getServerHost(array $params): string
{
    $server = getNestedValue($params, 'server');
    $serverDetails = getNestedValue($params, 'serverdetails');

    $candidates = [
        (string) ($params['serverhostname'] ?? ''),
        (string) ($params['serverip'] ?? ''),
        (string) getNestedValue($server, 'hostname'),
        (string) getNestedValue($server, 'ipaddress'),
        (string) getNestedValue($serverDetails, 'hostname'),
        (string) getNestedValue($serverDetails, 'ipaddress'),
    ];

    foreach ($candidates as $value) {
        if (trim($value) !== '') {
            return trim($value);
        }
    }

    $serverData = getServerDataFromDatabase($params);
    foreach (['hostname', 'ipaddress'] as $field) {
        if (isset($serverData[$field]) && trim((string) $serverData[$field]) !== '') {
            return trim((string) $serverData[$field]);
        }
    }

    return '';
}

function getServerApiToken(array $params): string
{
    $server = getNestedValue($params, 'server');
    $serverDetails = getNestedValue($params, 'serverdetails');

    $candidates = [
        (string) ($params['serveraccesshash'] ?? ''),
        (string) ($params['serverpassword'] ?? ''),
        (string) ($params['password'] ?? ''),
        (string) getNestedValue($server, 'accesshash'),
        (string) getNestedValue($server, 'password'),
        (string) getNestedValue($serverDetails, 'accesshash'),
        (string) getNestedValue($serverDetails, 'password'),
    ];

    foreach ($candidates as $value) {
        if (trim($value) !== '') {
            return trim($value);
        }
    }

    $serverData = getServerDataFromDatabase($params);
    foreach (['accesshash', 'password'] as $field) {
        if (isset($serverData[$field]) && trim((string) $serverData[$field]) !== '') {
            return trim((string) $serverData[$field]);
        }
    }

    return '';
}

function getServerDataFromDatabase(array $params): array
{
    $serverId = (int) ($params['serverid'] ?? 0);
    if ($serverId <= 0) {
        $serverId = (int) getNestedValue($params, 'server.id');
    }

    if ($serverId <= 0 || !class_exists(Capsule::class)) {
        return [];
    }

    try {
        $record = Capsule::table('tblservers')->where('id', $serverId)->first();
        if (!$record) {
            return [];
        }

        return (array) $record;
    } catch (Throwable $exception) {
        return [];
    }
}

function getNestedValue($source, string $path)
{
    if ($path === '') {
        return null;
    }

    $segments = explode('.', $path);
    $value = $source;

    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
            continue;
        }

        if (is_object($value) && isset($value->{$segment})) {
            $value = $value->{$segment};
            continue;
        }

        return null;
    }

    return $value;
}

function getCustomFieldValue(array $params, array $possibleKeys): string
{
    if (!isset($params['customfields']) || !is_array($params['customfields'])) {
        return '';
    }

    foreach ($possibleKeys as $key) {
        if (isset($params['customfields'][$key]) && trim((string) $params['customfields'][$key]) !== '') {
            return trim((string) $params['customfields'][$key]);
        }
    }

    return '';
}

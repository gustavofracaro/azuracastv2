<?php

declare(strict_types=1);

use AzuraCastV2\Api\AzuraCastApiClient;
use AzuraCastV2\Api\StringHelper;
use AzuraCastV2\Classes\Installer;
use AzuraCastV2\Classes\LogManager;
use AzuraCastV2\Classes\StationRepository;

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
        'Nome Público da Rádio' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Nome visível da rádio.',
        ],
        'Descrição' => [
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Descrição da estação.',
        ],
        'Fuso Horário' => [
            'Type' => 'text',
            'Default' => 'America/Sao_Paulo',
            'Description' => 'Exemplo: America/Sao_Paulo',
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
                'stationName' => $stats['name'] ?? ($params['configoption2'] ?? '-'),
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
    $host = StringHelper::normalizeBaseUrl((string) ($params['serverhostname'] ?: $params['serverip'] ?: ''));
    $token = (string) ($params['serveraccesshash'] ?? '');

    if ($host === '' || $token === '') {
        throw new RuntimeException('Hostname/IP e Token API Key são obrigatórios.');
    }

    return new AzuraCastApiClient($host, $token, new LogManager(__DIR__ . '/registro.log'));
}

function mapStationPayload(array $params): array
{
    return [
        'name' => (string) ($params['configoption2'] ?: $params['configoption1'] ?: 'radio-' . $params['serviceid']),
        'description' => (string) ($params['configoption3'] ?? ''),
        'timezone' => (string) ($params['configoption4'] ?? 'America/Sao_Paulo'),
        'max_listeners' => (int) ($params['configoption5'] ?? 100),
        'media_storage_quota' => (int) ($params['configoption6'] ?? 1024),
        'default_bitrate' => (int) ($params['configoption7'] ?? 128),
        'enable_autodj' => !empty($params['configoption8']),
    ];
}

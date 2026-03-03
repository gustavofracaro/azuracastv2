<?php

declare(strict_types=1);

use WHMCS\Module\Server\AzuraCast\Config;
use WHMCS\Module\Server\AzuraCast\Service;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

function azuracast_MetaData(): array
{
    return Config::metadata();
}

function azuracast_ConfigOptions(): array
{
    return Config::options();
}

function azuracast_CreateAccount(array $params)
{
    return (new Service($params))->createAccount();
}

function azuracast_SuspendAccount(array $params)
{
    return (new Service($params))->suspendAccount();
}

function azuracast_UnsuspendAccount(array $params)
{
    return (new Service($params))->unsuspendAccount();
}

function azuracast_TerminateAccount(array $params)
{
    return (new Service($params))->terminateAccount();
}

function azuracast_ChangePackage(array $params)
{
    return (new Service($params))->changePackage();
}

function azuracast_TestConnection(array $params): array
{
    return (new Service($params))->testConnection();
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
    return (new Service($params))->openPanel();
}

function azuracast_syncStationByShortName(array $params)
{
    return (new Service($params))->syncStationByShortName();
}

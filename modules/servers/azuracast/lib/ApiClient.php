<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\AzuraCast;

use RuntimeException;
use WHMCS\Database\Capsule;

final class ApiClient
{
    private const TIMEOUT = 60;
    private const CONNECT_TIMEOUT = 15;
    private const MAX_REDIRECTS = 5;

    public function __construct(private array $params)
    {
    }

    public function request(string $method, string $endpoint, ?array $payload = null): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('Configuração ausente: API Key/Access Hash do servidor WHMCS.');
        }

        $absoluteEndpoint = str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://');
        $baseUrls = $absoluteEndpoint ? [''] : $this->baseUrlCandidates();
        if ($baseUrls === []) {
            throw new RuntimeException('Configuração ausente: Nome do host/IP do servidor. Verifique o servidor no WHMCS.');
        }

        $lastError = null;
        foreach ($baseUrls as $baseUrl) {
            $result = $this->performRequest($baseUrl, $method, $endpoint, $payload, $apiKey);
            if ($result['curl_error'] === '') {
                return ['http_code' => $result['http_code'], 'body' => $result['body']];
            }

            $lastError = $result['curl_error'];
            if (!$this->isConnectionError($lastError)) {
                break;
            }
        }

        throw new RuntimeException('Erro CURL: ' . ($lastError ?: 'Falha de conexão desconhecida.'));
    }

    public function baseUrl(): string
    {
        $candidates = $this->baseUrlCandidates();
        return $candidates[0] ?? '';
    }

    public function apiKey(): string
    {
        $server = $this->serverData();
        $candidates = [
            (string) ($this->params['serveraccesshash'] ?? ''),
            (string) ($server['accesshash'] ?? ''),
            (string) ($this->params['serverpassword'] ?? ''),
            (string) ($server['password'] ?? ''),
            (string) ($this->params['password'] ?? ''),
            (string) ($this->params['serverusername'] ?? ''),
            (string) ($server['username'] ?? ''),
            (string) ($this->params['username'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $token = $this->normalizeToken($candidate);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    /** @return array{http_code:int,body:string,curl_error:string} */
    private function performRequest(string $baseUrl, string $method, string $endpoint, ?array $payload, string $apiKey): array
    {
        $url = (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://'))
            ? $endpoint
            : rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $verifySsl = !str_starts_with($url, 'http://');

        $headers = ['Accept: application/json', 'X-API-Key: ' . $apiKey];
        $bodyToSend = null;

        if ($payload !== null) {
            $bodyToSend = json_encode($payload);
            if ($bodyToSend === false) {
                throw new RuntimeException('Falha ao serializar payload JSON.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
        ]);

        if ($bodyToSend !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyToSend);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);

        if ($raw === false) {
            curl_close($ch);
            logModuleCall('azuracast', strtoupper($method) . ' ' . $endpoint, $payload, [
                'base_url' => $baseUrl,
                'url' => $url,
                'http_code' => $httpCode,
                'response' => false,
                'curl_error' => $curlError,
            ], $bodyToSend);

            return ['http_code' => $httpCode, 'body' => '', 'curl_error' => $curlError];
        }

        $body = (string) substr($raw, $headerSize);
        curl_close($ch);

        logModuleCall('azuracast', strtoupper($method) . ' ' . $endpoint, $payload, [
            'base_url' => $baseUrl,
            'url' => $url,
            'http_code' => $httpCode,
            'response' => $body,
            'curl_error' => $curlError,
        ], $bodyToSend);

        return ['http_code' => $httpCode, 'body' => $body, 'curl_error' => ''];
    }

    /** @return list<string> */
    private function baseUrlCandidates(): array
    {
        $server = $this->serverData();

        $hostCandidates = [
            (string) ($this->params['serverhostname'] ?? ''),
            (string) ($this->params['hostname'] ?? ''),
            (string) ($server['hostname'] ?? ''),
            (string) ($this->params['serverip'] ?? ''),
            (string) ($this->params['ipaddress'] ?? ''),
            (string) ($server['ipaddress'] ?? ''),
        ];

        $host = '';
        foreach ($hostCandidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                $host = $candidate;
                break;
            }
        }

        if ($host === '') {
            return [];
        }

        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            return [rtrim($host, '/')];
        }

        $serverPort = trim((string) ($this->params['serverport'] ?? ($server['port'] ?? '')));
        $secureRaw = (string) ($this->params['serversecure'] ?? ($server['secure'] ?? ''));
        $secure = $secureRaw === 'on' || $secureRaw === '1' || strtolower($secureRaw) === 'true';

        $httpPort = $serverPort !== '' ? (int) $serverPort : 80;
        $httpsPort = $serverPort !== '' ? (int) $serverPort : 443;

        $http = 'http://' . $host . ($httpPort === 80 ? '' : ':' . $httpPort);
        $https = 'https://' . $host . ($httpsPort === 443 ? '' : ':' . $httpsPort);

        return $secure ? [$https, $http] : [$http, $https];
    }

    /** @return array<string,mixed> */
    private function serverData(): array
    {
        $server = $this->params['server'] ?? [];
        if (is_array($server) && $server !== []) {
            return $server;
        }

        $serverId = (int) ($this->params['serverid'] ?? 0);

        if ($serverId <= 0) {
            $serviceId = (int) ($this->params['serviceid'] ?? 0);
            if ($serviceId > 0) {
                try {
                    $serverId = (int) (Capsule::table('tblhosting')->where('id', $serviceId)->value('server') ?? 0);
                } catch (\Throwable) {
                    $serverId = 0;
                }
            }
        }

        if ($serverId <= 0) {
            $productId = (int) (($this->params['pid'] ?? 0) ?: ($this->params['packageid'] ?? 0));
            if ($productId > 0) {
                try {
                    $serverGroupId = (int) (Capsule::table('tblproducts')->where('id', $productId)->value('servergroup') ?? 0);
                    if ($serverGroupId > 0) {
                        $serverId = (int) (Capsule::table('tblservergroupsrel')->where('groupid', $serverGroupId)->orderBy('serverid', 'asc')->value('serverid') ?? 0);
                    }
                } catch (\Throwable) {
                    $serverId = 0;
                }
            }
        }

        if ($serverId <= 0) {
            return [];
        }

        try {
            $row = Capsule::table('tblservers')->where('id', $serverId)->first();
            if (!$row) {
                return [];
            }

            return [
                'hostname' => (string) ($row->hostname ?? ''),
                'ipaddress' => (string) ($row->ipaddress ?? ''),
                'username' => (string) ($row->username ?? ''),
                'password' => (string) ($row->password ?? ''),
                'accesshash' => (string) ($row->accesshash ?? ''),
                'secure' => (string) ($row->secure ?? ''),
                'port' => (string) ($row->port ?? ''),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeToken(string $candidate): string
    {
        $candidate = trim($candidate);
        return $candidate === '' ? '' : (preg_replace('/\s+/', '', $candidate) ?? '');
    }

    private function isConnectionError(string $error): bool
    {
        $error = strtolower($error);
        return str_contains($error, 'failed to connect')
            || str_contains($error, 'could not resolve host')
            || str_contains($error, 'connection refused')
            || str_contains($error, 'timed out');
    }
}

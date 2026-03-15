<?php

declare(strict_types=1);

namespace AzuraCastV2\Api;

use AzuraCastV2\Classes\LogManager;
use RuntimeException;

class AzuraCastApiClient
{
    private string $baseUrl;
    private string $apiToken;
    private LogManager $log;
    private ?string $adminUsername;
    private ?string $adminPassword;

    public function __construct(string $baseUrl, string $apiToken, LogManager $log, ?string $adminUsername = null, ?string $adminPassword = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiToken = trim($apiToken);
        $this->log = $log;
        $this->adminUsername = $adminUsername !== null ? trim($adminUsername) : null;
        $this->adminPassword = $adminPassword !== null ? trim($adminPassword) : null;
    }

    public function testConnection(): array
    {
        try {
            $result = $this->request('GET', '/admin/stations');

            return [
                'ok' => true,
                'message' => 'Conectado com sucesso.',
                'result' => $result,
            ];
        } catch (RuntimeException $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function createStation(array $payload): array
    {
        return $this->request('POST', '/admin/stations', $payload);
    }

    public function updateStation(int $stationId, array $payload): array
    {
        return $this->request('PUT', '/admin/station/' . $stationId, $payload);
    }

    public function deleteStation(int $stationId): array
    {
        return $this->request('DELETE', '/admin/station/' . $stationId);
    }

    public function getStationOverview(int $stationId): array
    {
        return $this->request('GET', '/station/' . $stationId . '/status');
    }

    public function getStationPlaylists(int $stationId): array
    {
        return $this->request('GET', '/station/' . $stationId . '/playlists');
    }

    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $response = $this->performRequest($method, $endpoint, $payload, false);

        if ($this->isAuthenticationFailure($response['httpCode'], $response['body']) && $this->hasAdminCredentials()) {
            $this->log->error('Autenticação por token falhou, tentando fallback com usuário/senha admin. endpoint=' . $endpoint);
            $response = $this->performRequest($method, $endpoint, $payload, true);
        }

        $decoded = json_decode($response['body'], true);

        if ($response['httpCode'] < 200 || $response['httpCode'] >= 300) {
            $msg = is_array($decoded) ? ($decoded['message'] ?? null) : null;
            if (!is_string($msg) || trim($msg) === '') {
                $msg = 'HTTP ' . $response['httpCode'] . ' retornado pela API.';
            }

            if ($this->isAuthenticationFailure($response['httpCode'], $response['body'])) {
                $msg = 'API recusou a autenticação. Verifique Token API e, se necessário, usuário/senha administrativos do servidor no WHMCS.';
            }

            $this->log->error('Resposta inválida API: ' . $msg . ' | endpoint=' . $endpoint);
            throw new RuntimeException((string) $msg);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta da API em formato inválido.');
        }

        return $decoded;
    }

    private function performRequest(string $method, string $endpoint, array $payload, bool $useBasicAuth): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        if (!$ch) {
            throw new RuntimeException('Não foi possível iniciar conexão cURL.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiToken,
            'Authorization: Bearer ' . $this->apiToken,
            'X-Requested-With: XMLHttpRequest',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'WHMCS-AzuraCastV2-Module/1.0',
            CURLOPT_HEADER => false,
        ]);

        if ($useBasicAuth && $this->hasAdminCredentials()) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, (string) $this->adminUsername . ':' . (string) $this->adminPassword);
        }

        if (!empty($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                curl_close($ch);
                throw new RuntimeException('Falha ao serializar payload JSON da API.');
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $error !== '') {
            $this->log->error('Erro de conexão API: ' . $error . ' | endpoint=' . $endpoint);
            throw new RuntimeException('Erro de conexão com API: ' . $error);
        }

        return [
            'httpCode' => $httpCode,
            'body' => (string) $responseBody,
        ];
    }

    private function hasAdminCredentials(): bool
    {
        return !empty($this->adminUsername) && !empty($this->adminPassword);
    }

    private function isAuthenticationFailure(int $httpCode, string $body): bool
    {
        if (in_array($httpCode, [401, 403], true)) {
            return true;
        }

        return stripos($body, 'You must be logged in to access this page') !== false
            || stripos($body, 'Access Denied') !== false
            || stripos($body, 'Invalid API key') !== false;
    }
}

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

    public function __construct(string $baseUrl, string $apiToken, LogManager $log)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiToken = trim($apiToken);
        $this->log = $log;
    }

    public function testConnection(): array
    {
        try {
            $result = $this->request('GET', '/api/frontend/account/me');

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
        return $this->request('POST', '/api/admin/stations', $payload);
    }

    public function updateStation(int $stationId, array $payload): array
    {
        return $this->request('PUT', '/api/admin/station/' . $stationId, $payload);
    }

    public function deleteStation(int $stationId): array
    {
        return $this->request('DELETE', '/api/admin/station/' . $stationId);
    }

    public function getStationOverview(int $stationId): array
    {
        return $this->request('GET', '/api/station/' . $stationId . '/status');
    }

    public function getStationPlaylists(int $stationId): array
    {
        return $this->request('GET', '/api/station/' . $stationId . '/playlists');
    }

    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        if (!$ch) {
            throw new RuntimeException('Não foi possível iniciar conexão cURL.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (!empty($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $error !== '') {
            $this->log->error('Erro de conexão API: ' . $error);
            throw new RuntimeException('Erro de conexão com API: ' . $error);
        }

        $decoded = json_decode($responseBody, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $decoded['message'] ?? ('HTTP ' . $httpCode . ' retornado pela API.');
            $this->log->error('Resposta inválida API: ' . $msg . ' | endpoint=' . $endpoint);
            throw new RuntimeException((string) $msg);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta da API em formato inválido.');
        }

        return $decoded;
    }
}

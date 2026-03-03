<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\AzuraCast;

use RuntimeException;

final class ApiClient
{
    public function __construct(private array $params)
    {
    }

    public function request(string $method, string $endpoint, ?array $payload = null): array
    {
        $baseUrl = rtrim($this->baseUrl(), '/');
        $apiKey = $this->apiKey();

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Configurações obrigatórias ausentes: URL/API Key.');
        }

        $url = $baseUrl . '/' . ltrim($endpoint, '/');
        $timeout = max(5, (int) ($this->params['configoption4'] ?? 60));
        $verifySsl = !empty($this->params['configoption3']);
        $followRedirects = !empty($this->params['configoption22']);
        $maxRedirects = max(1, (int) ($this->params['configoption23'] ?? 5));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar cURL.');
        }

        $headers = ['Accept: application/json', 'X-API-Key: ' . $apiKey];
        $body = null;

        if ($payload !== null) {
            $body = json_encode($payload);
            if ($body === false) {
                throw new RuntimeException('Falha ao serializar JSON.');
            }
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => $maxRedirects,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        logModuleCall('azuracast', strtoupper($method) . ' ' . $endpoint, $payload, [
            'http_code' => $httpCode,
            'response' => $responseBody,
            'curl_error' => $error,
        ], $body);

        if ($responseBody === false) {
            throw new RuntimeException('Erro CURL: ' . $error);
        }

        return ['http_code' => $httpCode, 'body' => (string) $responseBody];
    }

    public function baseUrl(): string
    {
        $cfg = trim((string) ($this->params['configoption1'] ?? ''));
        if ($cfg !== '') {
            return $cfg;
        }

        $host = trim((string) ($this->params['serverhostname'] ?? ''));
        if ($host === '') {
            $host = trim((string) ($this->params['serverip'] ?? ''));
        }
        if ($host === '') {
            return '';
        }
        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            return rtrim($host, '/');
        }

        $secure = (string) ($this->params['serversecure'] ?? '') === 'on';
        return ($secure ? 'https://' : 'http://') . $host;
    }

    public function apiKey(): string
    {
        $key = trim((string) ($this->params['configoption2'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        $accessHash = trim((string) ($this->params['serveraccesshash'] ?? ''));
        if ($accessHash !== '') {
            return $accessHash;
        }

        return trim((string) ($this->params['serverpassword'] ?? ''));
    }
}

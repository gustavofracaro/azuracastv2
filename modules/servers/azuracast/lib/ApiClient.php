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
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('Configurações obrigatórias ausentes: API Key.');
        }

        $timeout = max(5, (int) ($this->params['configoption4'] ?? 60));
        $verifySsl = !empty($this->params['configoption3']);
        $followRedirects = !empty($this->params['configoption22']);
        $maxRedirects = max(1, (int) ($this->params['configoption23'] ?? 5));

        $baseUrls = $this->baseUrlCandidates();
        if ($baseUrls === []) {
            throw new RuntimeException('Configurações obrigatórias ausentes: URL do AzuraCast.');
        }

        $lastError = null;
        $lastResponse = null;

        foreach ($baseUrls as $baseUrl) {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
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
                CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
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
                'base_url' => $baseUrl,
                'http_code' => $httpCode,
                'response' => $responseBody,
                'curl_error' => $error,
            ], $body);

            if ($responseBody !== false) {
                return ['http_code' => $httpCode, 'body' => (string) $responseBody];
            }

            $lastError = $error;
            $lastResponse = ['http_code' => $httpCode, 'body' => ''];

            if ($this->isConnectionError($error) === false) {
                break;
            }
        }

        if ($lastError !== null) {
            throw new RuntimeException('Erro CURL: ' . $lastError);
        }

        return $lastResponse ?? ['http_code' => 0, 'body' => ''];
    }

    public function baseUrl(): string
    {
        $candidates = $this->baseUrlCandidates();
        return $candidates[0] ?? '';
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

    /**
     * @return list<string>
     */
    private function baseUrlCandidates(): array
    {
        $fromConfig = trim((string) ($this->params['configoption1'] ?? ''));
        if ($fromConfig !== '') {
            return [rtrim($fromConfig, '/')];
        }

        $host = trim((string) ($this->params['serverhostname'] ?? ''));
        if ($host === '') {
            $host = trim((string) ($this->params['serverip'] ?? ''));
        }
        if ($host === '') {
            return [];
        }

        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            return [rtrim($host, '/')];
        }

        $serverPort = trim((string) ($this->params['serverport'] ?? ''));
        $secureFlag = (string) ($this->params['serversecure'] ?? '');
        $secureExplicit = $secureFlag === 'on' || $secureFlag === '1';

        $httpPort = $serverPort !== '' ? (int) $serverPort : 80;
        $httpsPort = $serverPort !== '' ? (int) $serverPort : 443;

        $httpUrl = 'http://' . $host . ($httpPort !== 80 ? ':' . $httpPort : '');
        $httpsUrl = 'https://' . $host . ($httpsPort !== 443 ? ':' . $httpsPort : '');

        if ($secureExplicit) {
            return [$httpsUrl, $httpUrl];
        }

        // Quando WHMCS não está marcado como seguro, tenta HTTP e fallback HTTPS.
        return [$httpUrl, $httpsUrl];
    }

    private function isConnectionError(string $error): bool
    {
        $error = strtolower($error);
        return str_contains($error, 'failed to connect')
            || str_contains($error, 'could not resolve host')
            || str_contains($error, 'timed out')
            || str_contains($error, 'connection refused');
    }
}

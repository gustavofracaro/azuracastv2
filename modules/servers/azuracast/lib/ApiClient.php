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
            throw new RuntimeException('Configuração ausente: API Key. Preencha Access Hash, Senha ou Usuário do servidor WHMCS.');
        }

        $absoluteEndpoint = str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://');

        $baseUrls = $absoluteEndpoint ? [''] : $this->baseUrlCandidates();
        if ($baseUrls === []) {
            throw new RuntimeException('Configuração ausente: Nome do host/IP do servidor. Verifique o servidor WHMCS (Hostname/IP) ou use endpoint absoluto (https://...).');
        }

        $timeout = max(5, (int) ($this->params['configoption2'] ?? 60));
        $verifySsl = !empty($this->params['configoption1']);
        $followRedirects = !empty($this->params['configoption20']);
        $maxRedirects = max(1, (int) ($this->params['configoption21'] ?? 5));

        $lastError = null;

        foreach ($baseUrls as $baseUrl) {
            $result = $this->performRequest($baseUrl, $method, $endpoint, $payload, $apiKey, $timeout, $verifySsl, $followRedirects, $maxRedirects);

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
        $candidates = [
            (string) ($this->params['serveraccesshash'] ?? ''),
            (string) ($this->params['serverpassword'] ?? ''),
            (string) ($this->params['password'] ?? ''),
            (string) ($this->params['serverusername'] ?? ''),
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
    private function performRequest(
        string $baseUrl,
        string $method,
        string $endpoint,
        ?array $payload,
        string $apiKey,
        int $timeout,
        bool $verifySsl,
        bool $followRedirects,
        int $maxRedirects
    ): array {
        $url = (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://'))
            ? $endpoint
            : rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

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
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => $maxRedirects,
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
                'http_code' => $httpCode,
                'response' => false,
                'curl_error' => $curlError,
            ], $bodyToSend);

            return ['http_code' => $httpCode, 'body' => '', 'curl_error' => $curlError];
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = (string) substr($raw, $headerSize);
        curl_close($ch);

        if (in_array($httpCode, [301, 302, 307, 308], true) && !$followRedirects) {
            $location = $this->extractLocationHeader($rawHeaders);
            if ($location !== '') {
                $nextBaseUrl = $this->deriveBaseUrlFromLocation($location, $baseUrl);
                if ($nextBaseUrl !== '') {
                    return $this->performRequest($nextBaseUrl, $method, $endpoint, $payload, $apiKey, $timeout, $verifySsl, false, $maxRedirects - 1);
                }
            }
        }

        logModuleCall('azuracast', strtoupper($method) . ' ' . $endpoint, $payload, [
            'base_url' => $baseUrl,
            'http_code' => $httpCode,
            'response' => $body,
            'curl_error' => $curlError,
        ], $bodyToSend);

        return ['http_code' => $httpCode, 'body' => $body, 'curl_error' => ''];
    }

    /** @return list<string> */
    private function baseUrlCandidates(): array
    {
        $server = $this->params['server'] ?? [];
        if (!is_array($server)) {
            $server = [];
        }

        $hostCandidates = [
            (string) ($this->params['serverhostname'] ?? ''),
            (string) ($this->params['hostname'] ?? ''),
            (string) ($server['hostname'] ?? ''),
            (string) ($this->params['serverip'] ?? ''),
            (string) ($this->params['ipaddress'] ?? ''),
            (string) ($server['ipaddress'] ?? ''),
            (string) ($this->params['servername'] ?? ''),
            (string) ($server['name'] ?? ''),
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


    private function normalizeToken(string $candidate): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        // Access Hash do WHMCS pode conter quebras de linha e espaços.
        return preg_replace('/\s+/', '', $candidate) ?? '';
    }

    private function isConnectionError(string $error): bool
    {
        $error = strtolower($error);
        return str_contains($error, 'failed to connect')
            || str_contains($error, 'could not resolve host')
            || str_contains($error, 'connection refused')
            || str_contains($error, 'timed out');
    }

    private function extractLocationHeader(string $headers): string
    {
        foreach (preg_split('/\r\n|\n|\r/', $headers) as $line) {
            if (stripos($line, 'Location:') === 0) {
                return trim(substr($line, 9));
            }
        }

        return '';
    }

    private function deriveBaseUrlFromLocation(string $location, string $fallbackBase): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            $parts = parse_url($location);
            if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                return '';
            }

            $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
            return $parts['scheme'] . '://' . $parts['host'] . $port;
        }

        return $fallbackBase;
    }
}

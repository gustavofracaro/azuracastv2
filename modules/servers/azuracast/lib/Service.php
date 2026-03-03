<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\AzuraCast;

use RuntimeException;
use Throwable;
use WHMCS\Database\Capsule;

final class Service
{
    private ApiClient $api;
    private int $serviceId;
    private int $productId;
    private string $stationName;

    public function __construct(private array $params)
    {
        $this->api = new ApiClient($params);
        $this->serviceId = (int) ($params['serviceid'] ?? 0);
        $this->productId = (int) ($params['pid'] ?? 0);
        $this->stationName = $this->resolveStationName();
    }

    public function createAccount(): string
    {
        try {
            $stationId = $this->getStationId();
            if (!empty($stationId)) {
                return 'Serviço já possui station_id salvo: ' . $stationId;
            }

            $payload = $this->basePayload();
            $payload = $this->mergeTemplate($payload, (string) ($this->params['configoption16'] ?? ''));
            $payload = $this->mergeCustomFields($payload);

            $result = $this->api->request('POST', (string) ($this->params['configoption3'] ?? '/api/admin/stations'), $payload);
            $this->assertSuccess($result, 'Falha ao criar estação.');

            $decoded = json_decode($result['body'], true);
            $newId = is_array($decoded) ? ($decoded['id'] ?? ($decoded['station']['id'] ?? null)) : null;
            if (empty($newId)) {
                return 'Conta criada, mas a API não retornou station_id.';
            }

            $this->saveStationId((string) $newId);
            return 'success';
        } catch (Throwable $e) {
            return 'Erro ao provisionar: ' . $e->getMessage();
        }
    }

    public function suspendAccount(): string
    {
        return $this->stationAction('PUT', (string) ($this->params['configoption6'] ?? ''), (string) ($this->params['configoption17'] ?? '{"is_enabled":false}'));
    }

    public function unsuspendAccount(): string
    {
        return $this->stationAction('PUT', (string) ($this->params['configoption7'] ?? ''), (string) ($this->params['configoption18'] ?? '{"is_enabled":true}'));
    }

    public function terminateAccount(): string
    {
        try {
            $id = $this->requireStationId();
            $endpoint = $this->resolveStationEndpoint((string) ($this->params['configoption5'] ?? ''), $id);
            $result = $this->api->request('DELETE', $endpoint);
            $this->assertSuccess($result, 'Falha ao remover estação.');
            $this->saveStationId('');
            return 'success';
        } catch (Throwable $e) {
            return 'Erro no cancelamento: ' . $e->getMessage();
        }
    }

    public function changePackage(): string
    {
        try {
            $id = $this->requireStationId();
            $endpoint = $this->resolveStationEndpoint((string) ($this->params['configoption4'] ?? ''), $id);
            $payload = [
                'frontend_config' => [
                    'port' => (int) ($this->params['configoption13'] ?? 8000),
                    'max_listeners' => (int) ($this->params['configoption15'] ?? 0),
                ],
                'backend_config' => [
                    'port' => (int) ($this->params['configoption14'] ?? 8005),
                ],
            ];
            $payload = $this->mergeTemplate($payload, (string) ($this->params['configoption19'] ?? ''));
            $payload = $this->mergeCustomFields($payload);

            $result = $this->api->request('PUT', $endpoint, $payload);
            $this->assertSuccess($result, 'Falha ao atualizar pacote.');
            return 'success';
        } catch (Throwable $e) {
            return 'Erro ao alterar pacote: ' . $e->getMessage();
        }
    }

    public function testConnection(): array
    {
        try {
            $result = $this->api->request('GET', '/api/status');
            return ['success' => $result['http_code'] >= 200 && $result['http_code'] < 300, 'error' => $result['http_code'] >= 300 ? 'HTTP ' . $result['http_code'] . ' - ' . $result['body'] : ''];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function openPanel()
    {
        try {
            return ['success' => true, 'redirectTo' => rtrim($this->api->baseUrl(), '/') . '/station/' . rawurlencode($this->requireStationId())];
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function syncStationByShortName(): string
    {
        try {
            $short = $this->buildShortName();
            $result = $this->api->request('GET', '/api/admin/stations');
            $this->assertSuccess($result, 'Falha ao listar estações.');
            $stations = json_decode($result['body'], true);
            if (!is_array($stations)) {
                return 'Resposta inválida ao listar estações.';
            }
            foreach ($stations as $station) {
                if (is_array($station) && ($station['short_name'] ?? '') === $short && !empty($station['id'])) {
                    $this->saveStationId((string) $station['id']);
                    return 'success';
                }
            }
            return 'Estação não encontrada para short_name: ' . $short;
        } catch (Throwable $e) {
            return 'Erro na sincronização: ' . $e->getMessage();
        }
    }

    private function stationAction(string $method, string $endpointTemplate, string $payloadJson): string
    {
        try {
            $id = $this->requireStationId();
            $endpoint = $this->resolveStationEndpoint($endpointTemplate, $id);
            $payload = $this->parseTemplateJson($payloadJson);
            $result = $this->api->request($method, $endpoint, $payload);
            $this->assertSuccess($result, 'Falha na ação da estação.');
            return 'success';
        } catch (Throwable $e) {
            return 'Erro na ação da estação: ' . $e->getMessage();
        }
    }

    private function resolveStationName(): string
    {
        $customFields = $this->params['customfields'] ?? [];
        $raw = is_array($customFields) ? (string) ($customFields['station_name'] ?? '') : '';
        $raw = trim($raw);
        if ($raw !== '') {
            return $raw;
        }

        $domain = trim((string) ($this->params['domain'] ?? ''));
        if ($domain !== '') {
            return 'Rádio ' . $domain;
        }

        return 'Rádio #' . $this->serviceId;
    }

    private function basePayload(): array
    {
        return [
            'name' => $this->stationName,
            'description' => 'Provisionado via WHMCS. Service ID: ' . $this->serviceId,
            'short_name' => $this->buildShortName(),
            'frontend_type' => (string) ($this->params['configoption11'] ?? 'icecast'),
            'backend_type' => (string) ($this->params['configoption12'] ?? 'liquidsoap'),
            'frontend_config' => [
                'port' => (int) ($this->params['configoption13'] ?? 8000),
                'max_listeners' => (int) ($this->params['configoption15'] ?? 0),
            ],
            'backend_config' => [
                'port' => (int) ($this->params['configoption14'] ?? 8005),
            ],
            'timezone' => (string) ($this->params['configoption9'] ?? 'America/Sao_Paulo'),
            'default_language' => (string) ($this->params['configoption10'] ?? 'pt_BR'),
            'enable_public_page' => true,
            'enable_streamers' => true,
        ];
    }

    private function buildShortName(): string
    {
        $prefix = trim((string) ($this->params['configoption8'] ?? 'radio-'));
        $candidate = $prefix . $this->serviceId;
        $customFields = $this->params['customfields'] ?? [];
        if (is_array($customFields) && !empty($customFields['azuracast_short_name'])) {
            $candidate = (string) $customFields['azuracast_short_name'];
        }
        $clean = preg_replace('/[^a-z0-9_\-]/', '', strtolower($candidate));
        return $clean ?: ('radio' . $this->serviceId);
    }

    private function parseTemplateJson(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $replaced = strtr($json, [
            '{{service_id}}' => (string) $this->serviceId,
            '{{domain}}' => (string) ($this->params['domain'] ?? ''),
            '{{username}}' => (string) ($this->params['username'] ?? ''),
            '{{station_short_name}}' => $this->buildShortName(),
        ]);

        $decoded = json_decode($replaced, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON inválido em payload template.');
        }

        return $decoded;
    }

    private function mergeTemplate(array $base, string $template): array
    {
        $extra = $this->parseTemplateJson($template);
        return $this->mergeRecursiveDistinct($base, $extra);
    }

    private function mergeCustomFields(array $payload): array
    {
        $customFields = $this->params['customfields'] ?? [];
        if (!is_array($customFields)) {
            return $payload;
        }

        foreach (['name','description','timezone','default_language','frontend_type','backend_type','max_bitrate','is_public','enable_public_page','enable_on_demand','enable_streamers','record_streams'] as $field) {
            $key = 'azuracast_' . $field;
            if (array_key_exists($key, $customFields) && $customFields[$key] !== '') {
                $payload[$field] = $this->castValue($customFields[$key]);
            }
        }

        if (!empty($customFields['azuracast_payload_json'])) {
            $payload = $this->mergeTemplate($payload, (string) $customFields['azuracast_payload_json']);
        }

        return $payload;
    }

    private function mergeRecursiveDistinct(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    private function castValue(mixed $value): mixed
    {
        $text = trim((string) $value);
        $lower = strtolower($text);
        if (in_array($lower, ['true','yes','on'], true)) {
            return true;
        }
        if (in_array($lower, ['false','no','off'], true)) {
            return false;
        }
        if (is_numeric($text)) {
            return str_contains($text, '.') ? (float) $text : (int) $text;
        }
        return $text;
    }

    private function getStationId(): ?string
    {
        $customFields = $this->params['customfields'] ?? [];
        if (!is_array($customFields)) {
            return null;
        }
        foreach (['station_id', 'Station ID', 'stationid', 'azuracast_station_id'] as $key) {
            if (!empty($customFields[$key])) {
                return trim((string) $customFields[$key]);
            }
        }
        return null;
    }

    private function requireStationId(): string
    {
        $id = $this->getStationId();
        if ($id === null || $id === '') {
            throw new RuntimeException('station_id não encontrado para este serviço.');
        }
        return $id;
    }

    private function resolveStationEndpoint(string $template, string $stationId): string
    {
        if (trim($template) === '') {
            throw new RuntimeException('Endpoint da ação não configurado.');
        }
        return str_replace('{station_id}', rawurlencode($stationId), $template);
    }

    private function assertSuccess(array $result, string $message): void
    {
        if (($result['http_code'] ?? 0) >= 300) {
            throw new RuntimeException($message . ' HTTP ' . $result['http_code'] . ': ' . ($result['body'] ?? ''));
        }
    }

    private function saveStationId(string $stationId): void
    {
        if ($this->serviceId <= 0 || $this->productId <= 0) {
            return;
        }

        $customField = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $this->productId)
            ->whereIn('fieldname', ['station_id', 'Station ID', 'stationid', 'azuracast_station_id'])
            ->orderBy('id', 'asc')
            ->first();

        if (!$customField) {
            return;
        }

        $exists = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $customField->id)
            ->where('relid', $this->serviceId)
            ->first();

        if ($exists) {
            Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $customField->id)
                ->where('relid', $this->serviceId)
                ->update(['value' => $stationId]);
            return;
        }

        Capsule::table('tblcustomfieldsvalues')->insert([
            'fieldid' => $customField->id,
            'relid' => $this->serviceId,
            'value' => $stationId,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\AzuraCast;

use RuntimeException;
use Throwable;
use WHMCS\Database\Capsule;

final class Service
{
    private const ENDPOINT_CREATE = '/api/admin/stations';
    private const ENDPOINT_STATION = '/api/admin/station/{station_id}';

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
            if ($this->getStationId()) {
                return 'Serviço já possui station_id salvo.';
            }

            $payload = $this->basePayload();
            $payload = $this->mergeCustomFields($payload);

            $result = $this->api->request('POST', self::ENDPOINT_CREATE, $payload);
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
        return $this->stationAction('PUT', ['is_enabled' => false]);
    }

    public function unsuspendAccount(): string
    {
        return $this->stationAction('PUT', ['is_enabled' => true]);
    }

    public function terminateAccount(): string
    {
        try {
            $endpoint = $this->resolveStationEndpoint(self::ENDPOINT_STATION, $this->requireStationId());
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
            $endpoint = $this->resolveStationEndpoint(self::ENDPOINT_STATION, $this->requireStationId());
            $payload = $this->mergeCustomFields([
                'frontend_config' => ['port' => 8000, 'max_listeners' => 0],
                'backend_config' => ['port' => 8005],
            ]);

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

    private function stationAction(string $method, array $payload): string
    {
        try {
            $endpoint = $this->resolveStationEndpoint(self::ENDPOINT_STATION, $this->requireStationId());
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
        $name = is_array($customFields) ? trim((string) ($customFields['station_name'] ?? '')) : '';
        if ($name !== '') {
            return $name;
        }

        $domain = trim((string) ($this->params['domain'] ?? ''));
        return $domain !== '' ? 'Rádio ' . $domain : 'Rádio #' . $this->serviceId;
    }

    private function basePayload(): array
    {
        return [
            'name' => $this->stationName,
            'description' => 'Provisionado via WHMCS. Service ID: ' . $this->serviceId,
            'short_name' => $this->buildShortName(),
            'frontend_type' => 'icecast',
            'backend_type' => 'liquidsoap',
            'frontend_config' => ['port' => 8000, 'max_listeners' => 0],
            'backend_config' => ['port' => 8005],
            'timezone' => 'America/Sao_Paulo',
            'default_language' => 'pt_BR',
            'enable_public_page' => true,
            'enable_streamers' => true,
        ];
    }

    private function buildShortName(): string
    {
        $customFields = $this->params['customfields'] ?? [];
        $candidate = is_array($customFields) && !empty($customFields['azuracast_short_name'])
            ? (string) $customFields['azuracast_short_name']
            : ('radio-' . $this->serviceId);

        $clean = preg_replace('/[^a-z0-9_\-]/', '', strtolower($candidate));
        return $clean ?: ('radio' . $this->serviceId);
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
            $extra = json_decode((string) $customFields['azuracast_payload_json'], true);
            if (is_array($extra)) {
                $payload = array_replace_recursive($payload, $extra);
            }
        }

        return $payload;
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

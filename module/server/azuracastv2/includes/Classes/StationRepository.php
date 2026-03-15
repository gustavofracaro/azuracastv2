<?php

declare(strict_types=1);

namespace AzuraCastV2\Classes;

use RuntimeException;
use WHMCS\Database\Capsule;

class StationRepository
{
    private int $serviceId;
    private int $serverId;

    public function __construct(array $params)
    {
        $this->serviceId = (int) ($params['serviceid'] ?? 0);
        $this->serverId = (int) ($params['serverid'] ?? 0);
    }

    public function saveStation(int $stationId, string $stationName, string $status, array $snapshot): void
    {
        Capsule::table('azuracastv2_stations')->updateOrInsert(
            ['service_id' => $this->serviceId],
            [
                'server_id' => $this->serverId,
                'azuracast_station_id' => $stationId,
                'station_name' => $stationName,
                'status' => $status,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public function findStationByServiceId(bool $required = true): ?array
    {
        $station = Capsule::table('azuracastv2_stations')->where('service_id', $this->serviceId)->first();

        if (!$station) {
            if ($required) {
                throw new RuntimeException('Estação não encontrada para o serviço atual.');
            }

            return null;
        }

        return (array) $station;
    }

    public function updateStatus(int $stationId, string $status): void
    {
        Capsule::table('azuracastv2_stations')
            ->where('service_id', $this->serviceId)
            ->where('azuracast_station_id', $stationId)
            ->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function updateSnapshot(int $stationId, array $snapshot): void
    {
        Capsule::table('azuracastv2_stations')
            ->where('service_id', $this->serviceId)
            ->where('azuracast_station_id', $stationId)
            ->update([
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function deleteByServiceId(): void
    {
        Capsule::table('azuracastv2_stations')->where('service_id', $this->serviceId)->delete();
    }
}

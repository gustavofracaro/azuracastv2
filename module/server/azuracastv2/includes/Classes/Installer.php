<?php

declare(strict_types=1);

namespace AzuraCastV2\Classes;

use WHMCS\Database\Capsule;

class Installer
{
    public static function ensureTables(): void
    {
        if (!Capsule::schema()->hasTable('azuracastv2_servers')) {
            Capsule::schema()->create('azuracastv2_servers', static function ($table): void {
                $table->increments('id');
                $table->integer('server_id')->unsigned();
                $table->string('base_url', 255);
                $table->string('api_token', 255);
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable('azuracastv2_stations')) {
            Capsule::schema()->create('azuracastv2_stations', static function ($table): void {
                $table->increments('id');
                $table->integer('service_id')->unsigned()->unique();
                $table->integer('server_id')->unsigned();
                $table->integer('azuracast_station_id')->unsigned();
                $table->string('station_name', 200);
                $table->string('status', 50)->default('active');
                $table->longText('snapshot')->nullable();
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable('azuracastv2_logs')) {
            Capsule::schema()->create('azuracastv2_logs', static function ($table): void {
                $table->increments('id');
                $table->string('level', 20);
                $table->text('message');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }
}

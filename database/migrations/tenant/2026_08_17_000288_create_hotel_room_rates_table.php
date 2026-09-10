<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `hotel_room_rates` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `hotel_room_id` int(10) unsigned NOT NULL
 * - `hotel_rate_id` int(10) unsigned NOT NULL
 * - `price` double NOT NULL DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `hotel_room_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hotel_room_id` int(10) unsigned NOT NULL,
  `hotel_rate_id` int(10) unsigned NOT NULL,
  `price` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hotel_room_rates_hotel_room_id_foreign` (`hotel_room_id`),
  KEY `hotel_room_rates_hotel_rate_id_foreign` (`hotel_rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_room_rates`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

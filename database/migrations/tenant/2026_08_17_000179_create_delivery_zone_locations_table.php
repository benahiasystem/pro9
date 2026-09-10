<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `delivery_zone_locations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `delivery_zone_id` bigint(20) unsigned NOT NULL
 * - `department_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `delivery_zone_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_zone_id` bigint(20) unsigned NOT NULL,
  `department_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_zone_locations_delivery_zone_id_foreign` (`delivery_zone_id`),
  KEY `idx_delivery_zone_ubigeo` (`department_id`,`province_id`,`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `delivery_zone_locations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

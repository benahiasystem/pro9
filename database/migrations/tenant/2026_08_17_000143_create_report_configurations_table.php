<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `report_configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `route_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `route_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `convert_pen` tinyint(1) NOT NULL DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `report_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `route_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `convert_pen` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_configurations_route_name_unique` (`route_name`),
  UNIQUE KEY `report_configurations_route_path_unique` (`route_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `report_configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

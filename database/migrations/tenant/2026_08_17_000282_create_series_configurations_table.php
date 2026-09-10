<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `series_configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `series_id` int(10) unsigned NOT NULL
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `series_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` int(10) unsigned NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `series_configurations_series_id_foreign` (`series_id`),
  KEY `series_configurations_series_index` (`series`),
  KEY `series_configurations_number_index` (`number`),
  KEY `series_configurations_document_type_id_foreign` (`document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `series_configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

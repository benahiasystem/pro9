<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `packaging` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned NOT NULL
 * - `user_id` int(10) unsigned DEFAULT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_extra_data` json DEFAULT NULL
 * - `establishment_id` int(10) unsigned DEFAULT NULL
 * - `quantity` decimal(8,2) DEFAULT '0.00'
 * - `number_packages` decimal(8,2) DEFAULT '0.00'
 * - `item` json DEFAULT NULL
 * - `observation` longtext COLLATE utf8mb4_unicode_ci
 * - `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_start` date DEFAULT NULL
 * - `time_start` time DEFAULT NULL
 * - `date_end` date DEFAULT NULL
 * - `time_end` time DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `packaging_collaborator` text COLLATE utf8mb4_unicode_ci
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `packaging` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_extra_data` json DEFAULT NULL,
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(8,2) DEFAULT '0.00',
  `number_packages` decimal(8,2) DEFAULT '0.00',
  `item` json DEFAULT NULL,
  `observation` longtext COLLATE utf8mb4_unicode_ci,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `packaging_collaborator` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `packaging_fiscal_environment_foreign` (`fiscal_environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `packaging`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

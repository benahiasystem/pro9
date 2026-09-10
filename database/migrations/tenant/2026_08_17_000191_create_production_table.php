<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `production` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned DEFAULT '0'
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_id` int(10) unsigned DEFAULT '0'
 * - `inventory_id_reference` int(10) unsigned DEFAULT NULL
 * - `quantity` decimal(12,4) DEFAULT '0.0000' COMMENT 'Peso dle insumo '
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `machine_id` int(10) unsigned NOT NULL DEFAULT '0'
 * - `production_order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `comment` longtext COLLATE utf8mb4_unicode_ci
 * - `date_start` date DEFAULT NULL
 * - `time_start` time DEFAULT NULL
 * - `date_end` date DEFAULT NULL
 * - `time_end` time DEFAULT NULL
 * - `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_extra_data` json DEFAULT NULL
 * - `mix_date_start` date DEFAULT NULL
 * - `mix_time_start` time DEFAULT NULL
 * - `mix_date_end` date DEFAULT NULL
 * - `mix_time_end` time DEFAULT NULL
 * - `informative` tinyint(3) unsigned DEFAULT '0'
 * - `agreed` decimal(8,2) DEFAULT '0.00'
 * - `imperfect` decimal(8,2) DEFAULT '0.00'
 * - `proccess_type` longtext COLLATE utf8mb4_unicode_ci
 * - `production_collaborator` text COLLATE utf8mb4_unicode_ci
 * - `mix_collaborator` text COLLATE utf8mb4_unicode_ci
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `production` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT '0',
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int(10) unsigned DEFAULT '0',
  `inventory_id_reference` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(12,4) DEFAULT '0.0000' COMMENT 'Peso dle insumo ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `machine_id` int(10) unsigned NOT NULL DEFAULT '0',
  `production_order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `date_start` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_extra_data` json DEFAULT NULL,
  `mix_date_start` date DEFAULT NULL,
  `mix_time_start` time DEFAULT NULL,
  `mix_date_end` date DEFAULT NULL,
  `mix_time_end` time DEFAULT NULL,
  `informative` tinyint(3) unsigned DEFAULT '0',
  `agreed` decimal(8,2) DEFAULT '0.00',
  `imperfect` decimal(8,2) DEFAULT '0.00',
  `proccess_type` longtext COLLATE utf8mb4_unicode_ci,
  `production_collaborator` text COLLATE utf8mb4_unicode_ci,
  `mix_collaborator` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `production_fiscal_environment_foreign` (`fiscal_environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `production`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

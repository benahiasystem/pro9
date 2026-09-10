<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `mill` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `date_start` date DEFAULT NULL
 * - `time_start` time DEFAULT NULL
 * - `date_end` date DEFAULT NULL
 * - `time_end` time DEFAULT NULL
 * - `user_id` int(10) unsigned DEFAULT '0'
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `comment` longtext COLLATE utf8mb4_unicode_ci
 * - `mill_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `mill` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_start` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT '0',
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `mill_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mill_fiscal_environment_foreign` (`fiscal_environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `mill`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

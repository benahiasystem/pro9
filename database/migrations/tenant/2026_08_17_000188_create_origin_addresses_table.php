<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `origin_addresses` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `location_id` json NOT NULL
 * - `is_default` tinyint(1) NOT NULL DEFAULT '0'
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 * - `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VE'
 * - `establishment_id` int(11) DEFAULT NULL
 * - `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `origin_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` json NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VE',
  `establishment_id` int(11) DEFAULT NULL,
  `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `origin_addresses_country_id_foreign` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `origin_addresses`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `offline_configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `is_client` tinyint(1) NOT NULL DEFAULT '0'
 * - `token_server` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `url_server` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `offline_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_client` tinyint(1) NOT NULL DEFAULT '0',
  `token_server` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_server` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `offline_configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

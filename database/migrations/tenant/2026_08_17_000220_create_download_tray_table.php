<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `download_tray` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned DEFAULT NULL
 * - `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_PROCESS'
 * - `date_init` datetime DEFAULT NULL
 * - `date_end` datetime DEFAULT NULL
 * - `payload_request` text COLLATE utf8mb4_unicode_ci
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `download_tray` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_PROCESS',
  `date_init` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `payload_request` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `download_tray_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `download_tray`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

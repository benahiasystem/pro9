<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `module_levels` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `module_id` int(10) unsigned NOT NULL
 * - `route_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `route_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `label_menu` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `icon_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `module_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` int(10) unsigned NOT NULL,
  `route_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_menu` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_levels_module_id_foreign` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `module_levels`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

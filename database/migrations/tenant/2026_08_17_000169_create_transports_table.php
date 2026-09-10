<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `transports` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `plate_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `brand` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `tuc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `is_default` tinyint(1) NOT NULL DEFAULT '0'
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `transports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tuc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `transports`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

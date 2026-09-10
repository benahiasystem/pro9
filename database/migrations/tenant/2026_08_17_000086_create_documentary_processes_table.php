<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documentary_processes` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `description` text COLLATE utf8mb4_unicode_ci
 * - `price` decimal(10,5) NOT NULL DEFAULT '0.00000'
 * - `active` tinyint(1) NOT NULL DEFAULT '1'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `documentary_offices` longtext COLLATE utf8mb4_unicode_ci COMMENT 'etapas que contiene'
 * - `documentary_offices_order` longtext COLLATE utf8mb4_unicode_ci
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_processes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,5) NOT NULL DEFAULT '0.00000',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `documentary_offices` longtext COLLATE utf8mb4_unicode_ci COMMENT 'etapas que contiene',
  `documentary_offices_order` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_processes`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_periods` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `period` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Define si es dia, mes o año - D/M/Y'
 * - `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del periodo'
 * - `active` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si esta activo'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `period` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Define si es dia, mes o año - D/M/Y',
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del periodo',
  `active` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si esta activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_periods`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

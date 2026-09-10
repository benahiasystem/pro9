<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documentary_files_requirements` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre a mostrar'
 * - `file` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Define si tiene archivo'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_files_requirements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre a mostrar',
  `file` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Define si tiene archivo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_files_requirements`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

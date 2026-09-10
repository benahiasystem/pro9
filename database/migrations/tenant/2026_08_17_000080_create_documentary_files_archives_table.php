<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documentary_files_archives` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'usuario asociado'
 * - `documentary_file_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Solicitud asociada'
 * - `documentary_office_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'etapa asociada'
 * - `observation` longtext COLLATE utf8mb4_unicode_ci COMMENT 'observacion'
 * - `attached_file` longtext COLLATE utf8mb4_unicode_ci COMMENT 'etapa asociada'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `documentary_guides_number_id` int(10) unsigned NOT NULL DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_files_archives` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'usuario asociado',
  `documentary_file_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Solicitud asociada',
  `documentary_office_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'etapa asociada',
  `observation` longtext COLLATE utf8mb4_unicode_ci COMMENT 'observacion',
  `attached_file` longtext COLLATE utf8mb4_unicode_ci COMMENT 'etapa asociada',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `documentary_guides_number_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_files_archives`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

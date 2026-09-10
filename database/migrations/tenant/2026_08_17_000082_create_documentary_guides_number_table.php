<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documentary_guides_number` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `doc_file_id` int(10) unsigned DEFAULT '0' COMMENT 'Expediente relacionado'
 * - `doc_office_id` int(10) unsigned DEFAULT '0' COMMENT 'Etapa observada'
 * - `guide` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Especifica la guia'
 * - `origin` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Especifica la instucion'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `date_of_due` datetime DEFAULT NULL
 * - `observation` longtext COLLATE utf8mb4_unicode_ci
 * - `description` longtext COLLATE utf8mb4_unicode_ci
 * - `date_take` datetime DEFAULT NULL COMMENT 'Fecha estimada de finalización'
 * - `date_end` datetime DEFAULT NULL COMMENT 'Fecha de finalización'
 * - `documentary_guides_number_status_id` int(10) unsigned DEFAULT '0' COMMENT 'relacionado con documentary_guides_number_status'
 * - `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Responsable'
 * - `total_day` int(10) unsigned DEFAULT '1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_guides_number` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doc_file_id` int(10) unsigned DEFAULT '0' COMMENT 'Expediente relacionado',
  `doc_office_id` int(10) unsigned DEFAULT '0' COMMENT 'Etapa observada',
  `guide` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Especifica la guia',
  `origin` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Especifica la instucion',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `date_of_due` datetime DEFAULT NULL,
  `observation` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `date_take` datetime DEFAULT NULL COMMENT 'Fecha estimada de finalización',
  `date_end` datetime DEFAULT NULL COMMENT 'Fecha de finalización',
  `documentary_guides_number_status_id` int(10) unsigned DEFAULT '0' COMMENT 'relacionado con documentary_guides_number_status',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Responsable',
  `total_day` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_guides_number`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

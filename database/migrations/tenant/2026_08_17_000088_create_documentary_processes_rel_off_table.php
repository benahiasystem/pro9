<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documentary_processes_rel_off` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `doc_processes_id` int(10) unsigned DEFAULT '0' COMMENT 'Requerimiento relacionado'
 * - `doc_offices_id` int(10) unsigned DEFAULT '0' COMMENT 'Proceso relacionado'
 * - `active` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Status de la relacion'
 * - `order` int(10) unsigned DEFAULT '1' COMMENT 'Establece el orden del proceso'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_processes_rel_off` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doc_processes_id` int(10) unsigned DEFAULT '0' COMMENT 'Requerimiento relacionado',
  `doc_offices_id` int(10) unsigned DEFAULT '0' COMMENT 'Proceso relacionado',
  `active` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Status de la relacion',
  `order` int(10) unsigned DEFAULT '1' COMMENT 'Establece el orden del proceso',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_processes_rel_off`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

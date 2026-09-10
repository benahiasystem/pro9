<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documentary_file_offices` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `documentary_file_id` int(10) unsigned NOT NULL
 * - `documentary_office_id` int(10) unsigned NOT NULL
 * - `documentary_action_id` int(10) unsigned NOT NULL DEFAULT '0'
 * - `status` enum('POR DERIVAR','POR RECIBIR','EN PROCESO','FINALIZADO','ARCHIVADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POR DERIVAR'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `office_name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de la etapa'
 * - `process_name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre del tramite'
 * - `documentary_process_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Tramite relacionado'
 * - `complete` int(11) NOT NULL DEFAULT '0' COMMENT 'Define si la etapa esta completa'
 * - `start_date` datetime DEFAULT NULL COMMENT 'Fecha de inicio'
 * - `end_date` datetime DEFAULT NULL COMMENT 'Fecha de finalizacion'
 * - `days` int(10) unsigned DEFAULT '0' COMMENT 'dias para el tramite'
 * - `observation` int(11) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_file_offices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentary_file_id` int(10) unsigned NOT NULL,
  `documentary_office_id` int(10) unsigned NOT NULL,
  `documentary_action_id` int(10) unsigned NOT NULL DEFAULT '0',
  `status` enum('POR DERIVAR','POR RECIBIR','EN PROCESO','FINALIZADO','ARCHIVADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POR DERIVAR',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `office_name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de la etapa',
  `process_name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre del tramite',
  `documentary_process_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Tramite relacionado',
  `complete` int(11) NOT NULL DEFAULT '0' COMMENT 'Define si la etapa esta completa',
  `start_date` datetime DEFAULT NULL COMMENT 'Fecha de inicio',
  `end_date` datetime DEFAULT NULL COMMENT 'Fecha de finalizacion',
  `days` int(10) unsigned DEFAULT '0' COMMENT 'dias para el tramite',
  `observation` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `documentary_file_offices_documentary_file_id_foreign` (`documentary_file_id`),
  KEY `documentary_file_offices_documentary_office_id_foreign` (`documentary_office_id`),
  KEY `documentary_file_offices_documentary_action_id_foreign` (`documentary_action_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_file_offices`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

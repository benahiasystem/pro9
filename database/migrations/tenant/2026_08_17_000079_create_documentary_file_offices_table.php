<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documentary_file_offices`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `documentary_file_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `documentary_office_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `documentary_action_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `status`: enum('POR DERIVAR','POR RECIBIR','EN PROCESO','FINALIZADO','ARCHIVADO'); NOT NULL; DEFAULT POR DERIVAR; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `office_name`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Nombre de la etapa
 * - `process_name`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Nombre del tramite
 * - `documentary_process_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Tramite relacionado
 * - `complete`: int(11); NOT NULL; DEFAULT 0 — Define si la etapa esta completa
 * - `start_date`: datetime; NULL — Fecha de inicio
 * - `end_date`: datetime; NULL — Fecha de finalizacion
 * - `days`: int(10) unsigned; NULL; DEFAULT 0 — dias para el tramite
 * - `observation`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
// ######### FIN CAMBIO NELSON #########

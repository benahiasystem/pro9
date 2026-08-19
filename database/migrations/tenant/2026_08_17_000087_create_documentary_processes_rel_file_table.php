<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documentary_processes_rel_file`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `doc_processes_id`: int(10) unsigned; NULL; DEFAULT 0 — Requerimiento relacionado
 * - `doc_file_id`: int(10) unsigned; NULL; DEFAULT 0 — Expediente relacionado
 * - `doc_office_id`: int(10) unsigned; NULL; DEFAULT 0 — Etapa actual
 * - `stages`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Conjunto de etapas.
 * - `complete`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — Define si se ha completado
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_processes_rel_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doc_processes_id` int(10) unsigned DEFAULT '0' COMMENT 'Requerimiento relacionado',
  `doc_file_id` int(10) unsigned DEFAULT '0' COMMENT 'Expediente relacionado',
  `doc_office_id` int(10) unsigned DEFAULT '0' COMMENT 'Etapa actual',
  `stages` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Conjunto de etapas.',
  `complete` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Define si se ha completado',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_processes_rel_file`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documentary_files_archives`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — usuario asociado
 * - `documentary_file_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Solicitud asociada
 * - `documentary_office_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — etapa asociada
 * - `observation`: longtext; NULL; COLLATE utf8mb4_unicode_ci — observacion
 * - `attached_file`: longtext; NULL; COLLATE utf8mb4_unicode_ci — etapa asociada
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `documentary_guides_number_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
// ######### FIN CAMBIO NELSON #########

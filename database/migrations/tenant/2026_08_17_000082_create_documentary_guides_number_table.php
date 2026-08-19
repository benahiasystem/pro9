<?php
// ######### INICIO CAMBIO NELSON #########

/**
         * Run the migrations.
         *
         * @return void
         */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documentary_guides_number`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `doc_file_id`: int(10) unsigned; NULL; DEFAULT 0 — Expediente relacionado
 * - `doc_office_id`: int(10) unsigned; NULL; DEFAULT 0 — Etapa observada
 * - `guide`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Especifica la guia
 * - `origin`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Especifica la instucion
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_due`: datetime; NULL — Sin comentario definido en el esquema fuente.
 * - `observation`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `description`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_take`: datetime; NULL — Fecha estimada de finalización
 * - `date_end`: datetime; NULL — Fecha de finalización
 * - `documentary_guides_number_status_id`: int(10) unsigned; NULL; DEFAULT 0 — relacionado con documentary_guides_number_status
 * - `user_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Responsable
 * - `total_day`: int(10) unsigned; NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
// ######### FIN CAMBIO NELSON #########

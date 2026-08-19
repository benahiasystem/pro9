<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `dispatch_sale_notes`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_dispatch`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `time_dispatch`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `person_pick`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reference`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `person_dispatch`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `status`: tinyint(1); NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_sale_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_note_id` int(10) unsigned NOT NULL,
  `date_dispatch` date DEFAULT NULL,
  `time_dispatch` time DEFAULT NULL,
  `person_pick` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_dispatch` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispatch_sale_notes_sale_note_id_foreign` (`sale_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatch_sale_notes`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cat_affectation_igv_types`.
 *
 * Inventario de columnas:
 * - `id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `active`: tinyint(1); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `exportation`: tinyint(1); NULL — Sin comentario definido en el esquema fuente.
 * - `free`: tinyint(1); NULL — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_affectation_igv_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `exportation` tinyint(1) DEFAULT NULL,
  `free` tinyint(1) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_affectation_igv_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_affectation_igv_types`');
    }
};
// ######### FIN CAMBIO NELSON #########

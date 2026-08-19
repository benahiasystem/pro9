<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `soap_types`.
 *
 * Inventario de columnas:
 * - `id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `soap_types` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `soap_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `soap_types`');
    }
};
// ######### FIN CAMBIO NELSON #########

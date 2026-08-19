<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `track_api_peru_services`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `service`: int(10) unsigned; NULL; DEFAULT 0 — Tipo de servicio 1 => sunat/dni, 2 => validacion_multiple_cpe, 3 => CPE, 4 => tipo_de_cambio, 5 => printer_ticket
 * - `date_of_issue`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `track_api_peru_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service` int(10) unsigned DEFAULT '0' COMMENT 'Tipo de servicio  1 => sunat/dni, 2 => validacion_multiple_cpe, 3 => CPE, 4 => tipo_de_cambio, 5 => printer_ticket',
  `date_of_issue` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `track_api_peru_services`');
    }
};
// ######### FIN CAMBIO NELSON #########

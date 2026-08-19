<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `invoices`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `operation_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_of_due`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `operation_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_due` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoices_document_id_foreign` (`document_id`),
  KEY `invoices_operation_type_id_foreign` (`operation_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `invoices`');
    }
};
// ######### FIN CAMBIO NELSON #########

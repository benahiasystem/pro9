<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `item_unit_types`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `unit_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quantity_unit`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price1`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `price2`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `price3`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `price_default`: tinyint(1); NOT NULL; DEFAULT 2 — Sin comentario definido en el esquema fuente.
 * - `barcode`: varchar(150); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_unit_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_unit` decimal(12,4) NOT NULL,
  `price1` decimal(12,2) DEFAULT NULL,
  `price2` decimal(12,2) DEFAULT NULL,
  `price3` decimal(12,2) DEFAULT NULL,
  `price_default` tinyint(1) NOT NULL DEFAULT '2',
  `barcode` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_unit_types_unit_type_id_foreign` (`unit_type_id`),
  KEY `item_unit_types_item_id_foreign` (`item_id`),
  KEY `item_unit_types_barcode_index` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_unit_types`');
    }
};
// ######### FIN CAMBIO NELSON #########

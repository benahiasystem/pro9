<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `mill_items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `mill_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `height_to_mill`: decimal(12,3); NULL; DEFAULT 0.000 — Peso de entrada
 * - `total_height`: decimal(12,3); NULL; DEFAULT 0.000 — Peso dle insumo
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `item`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,3); NULL; DEFAULT 0.000 — Peso dle insumo
 * - `item_extra_data`: json; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `mill_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned DEFAULT '0',
  `mill_id` int(10) unsigned DEFAULT '0',
  `height_to_mill` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso de entrada',
  `total_height` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso dle insumo ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso dle insumo ',
  `item_extra_data` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `mill_items`');
    }
};
// ######### FIN CAMBIO NELSON #########

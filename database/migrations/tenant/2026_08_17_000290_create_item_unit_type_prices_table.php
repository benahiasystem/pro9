<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `item_unit_type_prices`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_unit_type_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price_label_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `is_active`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_unit_type_prices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_unit_type_id` int(10) unsigned NOT NULL,
  `price_label_id` int(10) unsigned NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_unit_price_label` (`item_unit_type_id`,`price_label_id`),
  KEY `item_unit_type_prices_item_unit_type_id_position_index` (`item_unit_type_id`),
  KEY `item_unit_type_prices_price_label_id_foreign` (`price_label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_unit_type_prices`');
    }
};
// ######### FIN CAMBIO NELSON #########

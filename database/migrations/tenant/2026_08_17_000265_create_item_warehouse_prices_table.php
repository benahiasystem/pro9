<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `item_warehouse_prices`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `warehouse_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price`: decimal(16,6); NOT NULL; DEFAULT 0.000000 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_warehouse_prices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `price` decimal(16,6) NOT NULL DEFAULT '0.000000',
  PRIMARY KEY (`id`),
  KEY `item_warehouse_prices_item_id_foreign` (`item_id`),
  KEY `item_warehouse_prices_warehouse_id_foreign` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_warehouse_prices`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `inventory_transfer_items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `inventory_transfer_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item_lots_group_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `item_lot_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_transfer_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_transfer_id` int(10) unsigned NOT NULL,
  `item_lots_group_id` int(10) unsigned DEFAULT NULL,
  `item_lot_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transfer_items_inventory_transfer_id_foreign` (`inventory_transfer_id`),
  KEY `inventory_transfer_items_item_lots_group_id_foreign` (`item_lots_group_id`),
  KEY `inventory_transfer_items_item_lot_id_foreign` (`item_lot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `inventory_transfer_items`');
    }
};

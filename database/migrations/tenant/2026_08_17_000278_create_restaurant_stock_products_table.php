<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `restaurant_stock_products`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `stock`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `quantity_reserved`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `has_supplies`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `restaurant_stock_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `stock` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `quantity_reserved` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `has_supplies` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `restaurant_stock_products_item_id_foreign` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_stock_products`');
    }
};

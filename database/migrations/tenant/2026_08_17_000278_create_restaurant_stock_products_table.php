<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `restaurant_stock_products` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned NOT NULL
 * - `stock` decimal(12,4) NOT NULL DEFAULT '0.0000'
 * - `quantity_reserved` decimal(12,4) NOT NULL DEFAULT '0.0000'
 * - `has_supplies` tinyint(1) NOT NULL DEFAULT '0'
 */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_stock_products`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

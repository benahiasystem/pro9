<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `order_form_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `order_form_id` int(10) unsigned NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `item` json NOT NULL
 * - `quantity` decimal(12,4) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `order_form_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_form_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_form_items_order_form_id_foreign` (`order_form_id`),
  KEY `order_form_items_item_id_foreign` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `order_form_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

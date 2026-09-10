<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `dispatch_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `dispatch_id` int(10) unsigned NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `item` json NOT NULL
 * - `quantity` decimal(12,4) NOT NULL
 * - `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci
 * - `additional_data` json DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dispatch_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci,
  `additional_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispatch_items_dispatch_id_foreign` (`dispatch_id`),
  KEY `dispatch_items_item_id_foreign` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatch_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

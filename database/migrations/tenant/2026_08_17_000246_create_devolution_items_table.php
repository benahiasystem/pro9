<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `devolution_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `devolution_id` int(10) unsigned NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `item` json NOT NULL
 * - `quantity` decimal(12,4) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `devolution_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `devolution_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `devolution_items_devolution_id_foreign` (`devolution_id`),
  KEY `devolution_items_item_id_foreign` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `devolution_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `guide_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `guide_id` int(10) unsigned NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `quantity` decimal(30,12) NOT NULL
 * - `unit_cost` decimal(30,12) NOT NULL
 * - `total` decimal(30,12) NOT NULL
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `guide_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `guide_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(30,12) NOT NULL,
  `unit_cost` decimal(30,12) NOT NULL,
  `total` decimal(30,12) NOT NULL,
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`),
  KEY `guide_items_guide_id_foreign` (`guide_id`),
  KEY `guide_items_item_id_foreign` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `guide_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

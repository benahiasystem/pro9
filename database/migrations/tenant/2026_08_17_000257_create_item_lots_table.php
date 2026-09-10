<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `item_lots` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date` date NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `warehouse_id` int(10) unsigned DEFAULT NULL
 * - `item_loteable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `item_loteable_id` int(10) unsigned NOT NULL
 * - `has_sale` tinyint(1) NOT NULL DEFAULT '0'
 * - `state` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_lots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `item_loteable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_loteable_id` int(10) unsigned NOT NULL,
  `has_sale` tinyint(1) NOT NULL DEFAULT '0',
  `state` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_lots_item_id_foreign` (`item_id`),
  KEY `item_lots_warehouse_id_foreign` (`warehouse_id`),
  KEY `item_lots_series_index` (`series`),
  KEY `item_lots_date_index` (`date`),
  KEY `item_lots_has_sale_index` (`has_sale`),
  KEY `item_lots_item_loteable_type_index` (`item_loteable_type`),
  KEY `item_lots_item_loteable_id_index` (`item_loteable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_lots`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

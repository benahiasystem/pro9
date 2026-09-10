<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `item_supplies` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned NOT NULL
 * - `individual_item_id` int(10) unsigned NOT NULL
 * - `quantity` decimal(13,3) DEFAULT '0.000'
 * - `unit_price` decimal(12,2) DEFAULT '0.00'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_supplies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `individual_item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(13,3) DEFAULT '0.000',
  `unit_price` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_supplies_item_id_foreign` (`item_id`),
  KEY `item_supplies_individual_item_id_foreign` (`individual_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_supplies`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

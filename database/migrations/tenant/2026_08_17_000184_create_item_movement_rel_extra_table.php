<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `item_movement_rel_extra` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned DEFAULT '0'
 * - `item_movement_id` int(10) unsigned DEFAULT '0'
 * - `item_color_id` int(10) unsigned DEFAULT '0'
 * - `item_status_id` int(10) unsigned DEFAULT '0'
 * - `item_unit_business_id` int(10) unsigned DEFAULT '0'
 * - `item_mold_cavities_id` int(10) unsigned DEFAULT '0'
 * - `item_package_measurements_id` int(10) unsigned DEFAULT '0'
 * - `item_units_per_package_id` int(10) unsigned DEFAULT '0'
 * - `item_mold_properties_id` int(10) unsigned DEFAULT '0'
 * - `item_product_family_id` int(10) unsigned DEFAULT '0'
 * - `item_size_id` int(10) unsigned DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_movement_rel_extra` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned DEFAULT '0',
  `item_movement_id` int(10) unsigned DEFAULT '0',
  `item_color_id` int(10) unsigned DEFAULT '0',
  `item_status_id` int(10) unsigned DEFAULT '0',
  `item_unit_business_id` int(10) unsigned DEFAULT '0',
  `item_mold_cavities_id` int(10) unsigned DEFAULT '0',
  `item_package_measurements_id` int(10) unsigned DEFAULT '0',
  `item_units_per_package_id` int(10) unsigned DEFAULT '0',
  `item_mold_properties_id` int(10) unsigned DEFAULT '0',
  `item_product_family_id` int(10) unsigned DEFAULT '0',
  `item_size_id` int(10) unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_movement_rel_extra_item_movement_id_foreign` (`item_movement_id`),
  KEY `index_item_status_id` (`item_status_id`),
  KEY `index_item_unit_business_id` (`item_unit_business_id`),
  KEY `index_item_mold_cavities_id` (`item_mold_cavities_id`),
  KEY `index_item_package_measurements_id` (`item_package_measurements_id`),
  KEY `index_item_units_per_package_id` (`item_units_per_package_id`),
  KEY `index_item_mold_properties_id` (`item_mold_properties_id`),
  KEY `index_item_product_family_id` (`item_product_family_id`),
  KEY `index_item_size_id` (`item_size_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_movement_rel_extra`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

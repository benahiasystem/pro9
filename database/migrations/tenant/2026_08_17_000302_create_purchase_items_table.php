<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `purchase_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `purchase_id` int(10) unsigned NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `item` json NOT NULL
 * - `item_lot_group_id` int(10) unsigned DEFAULT NULL
 * - `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `quantity` decimal(30,12) NOT NULL
 * - `unit_value` decimal(30,12) NOT NULL
 * - `date_of_due` date DEFAULT NULL
 * - `affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total_base_igv` decimal(30,12) NOT NULL
 * - `percentage_igv` decimal(30,12) NOT NULL
 * - `total_igv` decimal(30,12) NOT NULL
 * - `total_base_other_taxes` decimal(30,12) NOT NULL DEFAULT '0.00'
 * - `percentage_other_taxes` decimal(30,12) NOT NULL DEFAULT '0.00'
 * - `total_other_taxes` decimal(30,12) NOT NULL DEFAULT '0.00'
 * - `total_taxes` decimal(30,12) NOT NULL
 * - `price_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `unit_price` decimal(30,12) NOT NULL
 * - `total_value` decimal(30,12) NOT NULL
 * - `total_charge` decimal(30,12) NOT NULL DEFAULT '0.00'
 * - `total_discount` decimal(30,12) NOT NULL DEFAULT '0.00'
 * - `total` decimal(30,12) NOT NULL
 * - `attributes` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `charges` json DEFAULT NULL
 * - `warehouse_id` int(10) unsigned DEFAULT NULL
 * - `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci
 * - `update_purchase_price` tinyint(1) NOT NULL DEFAULT '0'
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `item_lot_group_id` int(10) unsigned DEFAULT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(30,12) NOT NULL,
  `unit_value` decimal(30,12) NOT NULL,
  `date_of_due` date DEFAULT NULL,
  `affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_base_igv` decimal(30,12) NOT NULL,
  `percentage_igv` decimal(30,12) NOT NULL,
  `total_igv` decimal(30,12) NOT NULL,
  `total_base_other_taxes` decimal(30,12) NOT NULL DEFAULT '0.00',
  `percentage_other_taxes` decimal(30,12) NOT NULL DEFAULT '0.00',
  `total_other_taxes` decimal(30,12) NOT NULL DEFAULT '0.00',
  `total_taxes` decimal(30,12) NOT NULL,
  `price_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(30,12) NOT NULL,
  `total_value` decimal(30,12) NOT NULL,
  `total_charge` decimal(30,12) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(30,12) NOT NULL DEFAULT '0.00',
  `total` decimal(30,12) NOT NULL,
  `attributes` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `charges` json DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci,
  `update_purchase_price` tinyint(1) NOT NULL DEFAULT '0',
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`),
  KEY `purchase_items_purchase_id_foreign` (`purchase_id`),
  KEY `purchase_items_item_id_foreign` (`item_id`),
  KEY `purchase_items_affectation_igv_type_id_foreign` (`affectation_igv_type_id`),
  KEY `purchase_items_price_type_id_foreign` (`price_type_id`),
  KEY `purchase_items_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_items_item_lot_group_id_index` (`item_lot_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `purchase_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

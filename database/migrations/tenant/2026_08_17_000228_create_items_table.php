<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `preparation_area_id` int(10) unsigned DEFAULT NULL
 * - `second_name` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `description` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `text_filter` longtext COLLATE utf8mb4_unicode_ci
 * - `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `factory_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `barcode` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `technical_specifications` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `internal_id` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_of_due` date DEFAULT NULL
 * - `account_id` int(10) unsigned DEFAULT NULL
 * - `item_code_gs1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `sale_unit_price` decimal(16,6) NOT NULL
 * - `purchase_has_igv` tinyint(1) NOT NULL DEFAULT '1'
 * - `has_igv` tinyint(1) NOT NULL DEFAULT '1'
 * - `purchase_unit_price` decimal(16,6) NOT NULL DEFAULT '0.000000'
 * - `restrict_sale_cpe` tinyint(1) NOT NULL DEFAULT '0'
 * - `exchange_points` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos'
 * - `quantity_of_points` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'sistema por puntos'
 * - `commission_amount` decimal(8,2) DEFAULT NULL
 * - `line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `commission_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `suggested_price` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `sale_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `purchase_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `calculate_quantity` tinyint(1) NOT NULL DEFAULT '0'
 * - `sale_unit_price_set` decimal(16,6) DEFAULT NULL
 * - `is_set` tinyint(1) NOT NULL DEFAULT '0'
 * - `parent_item_id` int(10) unsigned DEFAULT NULL
 * - `favorite` tinyint(1) NOT NULL DEFAULT '0'
 * - `category_id` int(10) unsigned DEFAULT NULL
 * - `brand_id` int(10) unsigned DEFAULT NULL
 * - `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagen-no-disponible.jpg'
 * - `image_medium` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagen-no-disponible.jpg'
 * - `image_small` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagen-no-disponible.jpg'
 * - `stock` decimal(16,4) NOT NULL DEFAULT '0.0000'
 * - `stock_min` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `lots_enabled` tinyint(1) NOT NULL DEFAULT '0'
 * - `series_enabled` tinyint(1) NOT NULL DEFAULT '0'
 * - `percentage_of_profit` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `has_perception` tinyint(1) NOT NULL DEFAULT '0'
 * - `percentage_perception` decimal(12,2) DEFAULT NULL
 * - `attributes` json DEFAULT NULL
 * - `active` tinyint(1) NOT NULL DEFAULT '1'
 * - `hidden_search` tinyint(1) NOT NULL DEFAULT '0'
 * - `web_platform_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `warehouse_id` int(10) unsigned DEFAULT NULL
 * - `status` tinyint(4) NOT NULL DEFAULT '1'
 * - `is_dish` tinyint(1) NOT NULL DEFAULT '0'
 * - `apply_store` tinyint(1) NOT NULL DEFAULT '0'
 * - `apply_restaurant` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'visible en menu restaurante'
 * - `restaurant_favorite` tinyint(4) NOT NULL DEFAULT '0'
 * - `cod_digemid` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de producto DIGEMID'
 * - `sanitary` text COLLATE utf8mb4_unicode_ci COMMENT 'Registro sanitario'
 * - `is_for_production` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si es compuesto para produccion'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preparation_area_id` int(10) unsigned DEFAULT NULL,
  `second_name` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_filter` longtext COLLATE utf8mb4_unicode_ci,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `factory_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technical_specifications` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_id` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_due` date DEFAULT NULL,
  `account_id` int(10) unsigned DEFAULT NULL,
  `item_code_gs1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_unit_price` decimal(16,6) NOT NULL,
  `purchase_has_igv` tinyint(1) NOT NULL DEFAULT '1',
  `has_igv` tinyint(1) NOT NULL DEFAULT '1',
  `purchase_unit_price` decimal(16,6) NOT NULL DEFAULT '0.000000',
  `restrict_sale_cpe` tinyint(1) NOT NULL DEFAULT '0',
  `exchange_points` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos',
  `quantity_of_points` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'sistema por puntos',
  `commission_amount` decimal(8,2) DEFAULT NULL,
  `line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggested_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculate_quantity` tinyint(1) NOT NULL DEFAULT '0',
  `sale_unit_price_set` decimal(16,6) DEFAULT NULL,
  `is_set` tinyint(1) NOT NULL DEFAULT '0',
  `parent_item_id` int(10) unsigned DEFAULT NULL,
  `favorite` tinyint(1) NOT NULL DEFAULT '0',
  `category_id` int(10) unsigned DEFAULT NULL,
  `brand_id` int(10) unsigned DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagen-no-disponible.jpg',
  `image_medium` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagen-no-disponible.jpg',
  `image_small` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imagen-no-disponible.jpg',
  `stock` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `stock_min` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lots_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `series_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `percentage_of_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `has_perception` tinyint(1) NOT NULL DEFAULT '0',
  `percentage_perception` decimal(12,2) DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `hidden_search` tinyint(1) NOT NULL DEFAULT '0',
  `web_platform_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `is_dish` tinyint(1) NOT NULL DEFAULT '0',
  `apply_store` tinyint(1) NOT NULL DEFAULT '0',
  `apply_restaurant` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'visible en menu restaurante',
  `restaurant_favorite` tinyint(4) NOT NULL DEFAULT '0',
  `cod_digemid` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de producto DIGEMID',
  `sanitary` text COLLATE utf8mb4_unicode_ci COMMENT 'Registro sanitario',
  `is_for_production` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si es compuesto para produccion',
  PRIMARY KEY (`id`),
  KEY `items_item_type_id_foreign` (`item_type_id`),
  KEY `items_unit_type_id_foreign` (`unit_type_id`),
  KEY `items_currency_type_id_foreign` (`currency_type_id`),
  KEY `items_sale_affectation_igv_type_id_foreign` (`sale_affectation_igv_type_id`),
  KEY `items_purchase_affectation_igv_type_id_foreign` (`purchase_affectation_igv_type_id`),
  KEY `items_warehouse_id_foreign` (`warehouse_id`),
  KEY `items_account_id_foreign` (`account_id`),
  KEY `items_brand_id_foreign` (`brand_id`),
  KEY `items_category_id_foreign` (`category_id`),
  KEY `items_second_name_index` (`second_name`),
  KEY `items_description_index` (`description`),
  KEY `items_internal_id_index` (`internal_id`),
  KEY `items_item_code_index` (`item_code`),
  KEY `items_web_platform_id_foreign` (`web_platform_id`),
  KEY `items_parent_item_id_index` (`parent_item_id`),
  KEY `items_favorite_index` (`favorite`),
  KEY `items_factory_code_index` (`factory_code`),
  KEY `items_barcode_index` (`barcode`),
  KEY `items_preparation_area_id_foreign` (`preparation_area_id`),
  KEY `items_name_index` (`name`(191)),
  FULLTEXT KEY `items_text_filter_fulltext` (`text_filter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

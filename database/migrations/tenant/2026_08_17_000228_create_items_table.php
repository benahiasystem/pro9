<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(1000); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `preparation_area_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `second_name`: varchar(600); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(600); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `text_filter`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `model`: varchar(100); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `factory_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `barcode`: varchar(150); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `technical_specifications`: varchar(300); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `internal_id`: varchar(30); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_of_due`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `account_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `item_code_gs1`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unit_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sale_unit_price`: decimal(16,6); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `purchase_has_igv`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `has_igv`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `purchase_unit_price`: decimal(16,6); NOT NULL; DEFAULT 0.000000 — Sin comentario definido en el esquema fuente.
 * - `has_isc`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `restrict_sale_cpe`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `exchange_points`: tinyint(1); NOT NULL; DEFAULT 0 — sistema por puntos
 * - `quantity_of_points`: decimal(12,2); NOT NULL; DEFAULT 0.00 — sistema por puntos
 * - `commission_amount`: decimal(8,2); NULL — Sin comentario definido en el esquema fuente.
 * - `line`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `commission_type`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `amount_plastic_bag_taxes`: decimal(6,2); NOT NULL; DEFAULT 0.10 — Sin comentario definido en el esquema fuente.
 * - `system_isc_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `percentage_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `suggested_price`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `purchase_has_isc`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `purchase_system_isc_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `purchase_percentage_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `sale_affectation_igv_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `purchase_affectation_igv_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `calculate_quantity`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `sale_unit_price_set`: decimal(16,6); NULL — Sin comentario definido en el esquema fuente.
 * - `is_set`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `parent_item_id`: int(10) unsigned; NULL — Producto padre para variantes; contrato requerido por todos los buscadores.
 * - `favorite`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `category_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `brand_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `image`: varchar(255); NOT NULL; DEFAULT imagen-no-disponible.jpg; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `image_medium`: varchar(255); NOT NULL; DEFAULT imagen-no-disponible.jpg; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `image_small`: varchar(255); NOT NULL; DEFAULT imagen-no-disponible.jpg; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `stock`: decimal(16,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `stock_min`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `has_plastic_bag_taxes`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `lot_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `lots_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `series_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `percentage_of_profit`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `has_perception`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `percentage_perception`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `attributes`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `active`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `hidden_search`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `web_platform_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `warehouse_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `status`: tinyint(4); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `is_dish`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `apply_store`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `apply_restaurant`: tinyint(1); NOT NULL; DEFAULT 0 — visible en menu restaurante
 * - `restaurant_favorite`: tinyint(4); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `cod_digemid`: text; NULL; COLLATE utf8mb4_unicode_ci — Codigo de producto DIGEMID
 * - `sanitary`: text; NULL; COLLATE utf8mb4_unicode_ci — Registro sanitario
 * - `is_for_production`: tinyint(1); NOT NULL; DEFAULT 0 — Define si es compuesto para produccion
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ########### INICIO CONTRATO FLUJO DE PRODUCTOS ###########
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
  `has_isc` tinyint(1) NOT NULL DEFAULT '0',
  `restrict_sale_cpe` tinyint(1) NOT NULL DEFAULT '0',
  `exchange_points` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sistema por puntos',
  `quantity_of_points` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'sistema por puntos',
  `commission_amount` decimal(8,2) DEFAULT NULL,
  `line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_plastic_bag_taxes` decimal(6,2) NOT NULL DEFAULT '0.10',
  `system_isc_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
  `suggested_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `purchase_has_isc` tinyint(1) NOT NULL DEFAULT '0',
  `purchase_system_isc_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_percentage_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
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
  `has_plastic_bag_taxes` tinyint(1) NOT NULL DEFAULT '0',
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
  KEY `items_system_isc_type_id_foreign` (`system_isc_type_id`),
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
  KEY `items_purchase_system_isc_type_id_foreign` (`purchase_system_isc_type_id`),
  KEY `items_parent_item_id_index` (`parent_item_id`),
  KEY `items_favorite_index` (`favorite`),
  KEY `items_factory_code_index` (`factory_code`),
  KEY `items_barcode_index` (`barcode`),
  KEY `items_preparation_area_id_foreign` (`preparation_area_id`),
  KEY `items_name_index` (`name`(191)),
  FULLTEXT KEY `items_text_filter_fulltext` (`text_filter`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // ########### FIN CONTRATO FLUJO DE PRODUCTOS ###########
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `items`');
    }
};
// ######### FIN CAMBIO NELSON #########

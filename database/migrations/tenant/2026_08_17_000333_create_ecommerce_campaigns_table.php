<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `ecommerce_campaigns` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `discount_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage'
 * - `discount_value` decimal(12,4) NOT NULL DEFAULT '0.0000'
 * - `start_date` datetime DEFAULT NULL
 * - `end_date` datetime DEFAULT NULL
 * - `sp_product_ids` json DEFAULT NULL
 * - `status` tinyint(1) NOT NULL DEFAULT '1'
 * - `sp_countdown` tinyint(1) NOT NULL DEFAULT '0'
 * - `sp_discount_price` tinyint(1) NOT NULL DEFAULT '0'
 * - `sp_purchase_count` tinyint(1) NOT NULL DEFAULT '0'
 * - `sp_views_count` tinyint(1) NOT NULL DEFAULT '0'
 * - `sp_stock_alert` tinyint(1) NOT NULL DEFAULT '0'
 * - `sp_rating` tinyint(1) NOT NULL DEFAULT '0'
 * - `sp_stock_threshold` int(10) unsigned NOT NULL DEFAULT '10'
 * - `sp_views_min` int(11) NOT NULL DEFAULT '10'
 * - `sp_views_max` int(11) NOT NULL DEFAULT '50'
 * - `sp_purchase_min` int(11) NOT NULL DEFAULT '5'
 * - `sp_purchase_max` int(11) NOT NULL DEFAULT '30'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `ecommerce_campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `sp_product_ids` json DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sp_countdown` tinyint(1) NOT NULL DEFAULT '0',
  `sp_discount_price` tinyint(1) NOT NULL DEFAULT '0',
  `sp_purchase_count` tinyint(1) NOT NULL DEFAULT '0',
  `sp_views_count` tinyint(1) NOT NULL DEFAULT '0',
  `sp_stock_alert` tinyint(1) NOT NULL DEFAULT '0',
  `sp_rating` tinyint(1) NOT NULL DEFAULT '0',
  `sp_stock_threshold` int(10) unsigned NOT NULL DEFAULT '10',
  `sp_views_min` int(11) NOT NULL DEFAULT '10',
  `sp_views_max` int(11) NOT NULL DEFAULT '50',
  `sp_purchase_min` int(11) NOT NULL DEFAULT '5',
  `sp_purchase_max` int(11) NOT NULL DEFAULT '30',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ecommerce_campaigns_status_end_idx` (`status`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `ecommerce_campaigns`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

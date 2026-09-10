<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `orders` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `order_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `customer` json NOT NULL
 * - `shipping_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `items` json NOT NULL
 * - `total` decimal(12,2) NOT NULL
 * - `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `discount_coupon_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `discount_coupon_id` bigint(20) unsigned DEFAULT NULL
 * - `stock_discounted` tinyint(1) NOT NULL DEFAULT '0'
 * - `reference_payment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `document_external_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `number_document` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `status_order_id` tinyint(3) unsigned DEFAULT NULL
 * - `payment_status_order_id` tinyint(3) unsigned DEFAULT NULL
 * - `shipping_status_order_id` tinyint(3) unsigned DEFAULT NULL
 * - `tracking_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `purchase` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `deleted_at` timestamp NULL DEFAULT NULL
 * - `apply_restaurant` tinyint(1) DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer` json NOT NULL,
  `shipping_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `items` json NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_coupon_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_coupon_id` bigint(20) unsigned DEFAULT NULL,
  `stock_discounted` tinyint(1) NOT NULL DEFAULT '0',
  `reference_payment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_external_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number_document` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_order_id` tinyint(3) unsigned DEFAULT NULL,
  `payment_status_order_id` tinyint(3) unsigned DEFAULT NULL,
  `shipping_status_order_id` tinyint(3) unsigned DEFAULT NULL,
  `tracking_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `apply_restaurant` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_code_unique` (`order_code`),
  KEY `orders_status_order_id_foreign` (`status_order_id`),
  KEY `orders_payment_status_order_id_foreign` (`payment_status_order_id`),
  KEY `orders_shipping_status_order_id_foreign` (`shipping_status_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `orders`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

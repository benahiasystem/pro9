<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `orders`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `external_id`: char(36); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `customer`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `shipping_address`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `items`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_discount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `discount_coupon_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `discount_coupon_id`: bigint(20) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `stock_discounted`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `reference_payment`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `document_external_id`: char(36); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number_document`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `status_order_id`: tinyint(3) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `payment_status_order_id`: tinyint(3) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `shipping_status_order_id`: tinyint(3) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `purchase`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `deleted_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `apply_restaurant`: tinyint(1); NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
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
  `purchase` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `apply_restaurant` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
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
// ######### FIN CAMBIO NELSON #########

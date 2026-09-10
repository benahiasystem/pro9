<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `discount_coupons` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage'
 * - `amount` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `has_purchase_limits` tinyint(1) NOT NULL DEFAULT '0'
 * - `min_amount` decimal(12,2) DEFAULT NULL
 * - `max_amount` decimal(12,2) DEFAULT NULL
 * - `has_usage_limits` tinyint(1) NOT NULL DEFAULT '0'
 * - `max_total_uses` int(10) unsigned DEFAULT NULL
 * - `uses_count` int(10) unsigned NOT NULL DEFAULT '0'
 * - `max_uses_per_customer` int(10) unsigned DEFAULT NULL
 * - `expires_at` datetime DEFAULT NULL
 * - `free_shipping` tinyint(1) NOT NULL DEFAULT '0'
 * - `active` tinyint(1) NOT NULL DEFAULT '1'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `deleted_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `discount_coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `has_purchase_limits` tinyint(1) NOT NULL DEFAULT '0',
  `min_amount` decimal(12,2) DEFAULT NULL,
  `max_amount` decimal(12,2) DEFAULT NULL,
  `has_usage_limits` tinyint(1) NOT NULL DEFAULT '0',
  `max_total_uses` int(10) unsigned DEFAULT NULL,
  `uses_count` int(10) unsigned NOT NULL DEFAULT '0',
  `max_uses_per_customer` int(10) unsigned DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `free_shipping` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discount_coupons_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `discount_coupons`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

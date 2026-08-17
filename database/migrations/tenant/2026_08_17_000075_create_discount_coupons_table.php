<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `discount_coupons`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `code`: varchar(20); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `type`: enum('percentage','fixed'); NOT NULL; DEFAULT percentage; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `amount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `has_purchase_limits`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `min_amount`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `max_amount`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `has_usage_limits`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `max_total_uses`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `max_uses_per_customer`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `expires_at`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `free_shipping`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `active`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
  `max_uses_per_customer` int(10) unsigned DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `free_shipping` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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

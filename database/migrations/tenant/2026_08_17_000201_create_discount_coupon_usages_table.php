<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `discount_coupon_usages` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `discount_coupon_id` bigint(20) unsigned NOT NULL
 * - `person_id` int(10) unsigned DEFAULT NULL
 * - `order_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `discount_coupon_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discount_coupon_id` bigint(20) unsigned NOT NULL,
  `person_id` int(10) unsigned DEFAULT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discount_coupon_usages_discount_coupon_id_foreign` (`discount_coupon_id`),
  KEY `discount_coupon_usages_person_id_foreign` (`person_id`),
  KEY `discount_coupon_usages_order_id_foreign` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `discount_coupon_usages`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

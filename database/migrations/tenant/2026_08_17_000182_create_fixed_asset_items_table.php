<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `fixed_asset_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `internal_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `purchase_unit_price` decimal(16,6) NOT NULL
 * - `purchase_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `fixed_asset_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_unit_price` decimal(16,6) NOT NULL,
  `purchase_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fixed_asset_items_item_type_id_foreign` (`item_type_id`),
  KEY `fixed_asset_items_unit_type_id_foreign` (`unit_type_id`),
  KEY `fixed_asset_items_currency_type_id_foreign` (`currency_type_id`),
  KEY `fixed_asset_items_purchase_affectation_igv_type_id_foreign` (`purchase_affectation_igv_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `fixed_asset_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

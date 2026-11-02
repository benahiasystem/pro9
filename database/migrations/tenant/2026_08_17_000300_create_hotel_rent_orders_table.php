<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `hotel_rent_orders` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `hotel_rent_id` int(10) unsigned NOT NULL
 * - `order_number` int(11) NOT NULL
 * - `order_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `sale_note_id` int(10) unsigned DEFAULT NULL
 * - `establishment_id` int(10) unsigned DEFAULT NULL
 * - `operation_key` char(36) DEFAULT NULL, único
 * - `operation_hash` char(64) DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `hotel_rent_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hotel_rent_id` int(10) unsigned NOT NULL,
  `order_number` int(11) NOT NULL,
  `order_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `operation_key` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operation_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hotel_rent_orders_operation_key_unique` (`operation_key`),
  KEY `hotel_rent_orders_hotel_rent_id_foreign` (`hotel_rent_id`),
  KEY `hotel_rent_orders_sale_note_id_foreign` (`sale_note_id`),
  KEY `hotel_rent_orders_establishment_id_foreign` (`establishment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_rent_orders`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

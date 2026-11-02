<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `hotel_rent_item_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `hotel_rent_item_id` int(10) unsigned NOT NULL
 * - `date_of_payment` date NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `change` decimal(30,12) DEFAULT NULL
 * - `payment` decimal(30,12) NOT NULL
 * - `currency_type_id` varchar(255) DEFAULT NULL
 * - `original_amount` decimal(30,12) DEFAULT NULL
 * - `exchange_rate` decimal(18,8) DEFAULT NULL
 * - `exchange_rate_source` varchar(255) DEFAULT NULL
 * - `exchange_rate_date` date DEFAULT NULL
 * - `payment_method_snapshot` json DEFAULT NULL
 * - `source_sale_note_payment_id` int(10) unsigned DEFAULT NULL
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `hotel_rent_item_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hotel_rent_item_id` int(10) unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change` decimal(30,12) DEFAULT NULL,
  `payment` decimal(30,12) NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_amount` decimal(30,12) DEFAULT NULL,
  `exchange_rate` decimal(18,8) DEFAULT NULL,
  `exchange_rate_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_rate_date` date DEFAULT NULL,
  `payment_method_snapshot` json DEFAULT NULL,
  `source_sale_note_payment_id` int(10) unsigned DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hotel_receipt_source_unique` (`source_sale_note_payment_id`),
  KEY `hotel_rent_item_payments_hotel_rent_item_id_foreign` (`hotel_rent_item_id`),
  KEY `hotel_rent_item_payments_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `hotel_rent_item_payments_date_of_payment_index` (`date_of_payment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_rent_item_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

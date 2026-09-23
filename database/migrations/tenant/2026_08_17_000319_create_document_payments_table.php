<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `document_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `source_sale_note_payment_id` int(10) unsigned DEFAULT NULL, UNIQUE, FK a sale_note_payments
 * - `document_id` int(10) unsigned NOT NULL
 * - `date_of_payment` date NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `has_card` tinyint(1) NOT NULL DEFAULT '0'
 * - `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment_received` tinyint(1) DEFAULT NULL
 * - `change` decimal(12,2) DEFAULT NULL
 * - `payment` decimal(12,2) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `source_sale_note_payment_id` int(10) unsigned DEFAULT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_received` tinyint(1) DEFAULT NULL,
  `change` decimal(12,2) DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_payments_source_sale_note_unique` (`source_sale_note_payment_id`),
  KEY `document_payments_document_id_foreign` (`document_id`),
  KEY `document_payments_card_brand_id_foreign` (`card_brand_id`),
  KEY `document_payments_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `document_payments_date_of_payment_index` (`date_of_payment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `document_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

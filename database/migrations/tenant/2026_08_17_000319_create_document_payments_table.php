<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `document_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `date_of_payment` date NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `has_card` tinyint(1) NOT NULL DEFAULT '0'
 * - `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment_received` tinyint(1) DEFAULT NULL
 * - `change` decimal(12,2) DEFAULT NULL
 * - `payment` decimal(12,2) NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `exchange_rate` decimal(13,3) NOT NULL
 * - `exchange_rate_source` varchar(255) DEFAULT NULL
 * - `exchange_rate_date` date DEFAULT NULL
 * - `original_amount` decimal(12,2) NOT NULL
 * - `receipt_parent_id` int(10) unsigned DEFAULT NULL
 * - `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `payment_method_snapshot` json NOT NULL
 * - `igtf_status` varchar(16) NOT NULL DEFAULT 'not_applicable'
 * - `exemption_reason` varchar(255) DEFAULT NULL
 * - `operation_key` char(36) NOT NULL
 * - `reversed_at` timestamp NULL DEFAULT NULL
 * - `reversal_reason` varchar(255) DEFAULT NULL
 * - `reversed_by` int(10) unsigned DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_received` tinyint(1) DEFAULT NULL,
  `change` decimal(12,2) DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  -- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exchange_rate` decimal(13,3) NOT NULL,
  `exchange_rate_source` varchar(255) DEFAULT NULL,
  `exchange_rate_date` date DEFAULT NULL,
  `original_amount` decimal(12,2) NOT NULL,
  `receipt_parent_id` int(10) unsigned DEFAULT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method_snapshot` json NOT NULL,
  `igtf_status` varchar(16) NOT NULL DEFAULT 'not_applicable',
  `exemption_reason` varchar(255) DEFAULT NULL,
  `operation_key` char(36) NOT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  `reversed_by` int(10) unsigned DEFAULT NULL,
  UNIQUE KEY `document_payments_operation_unique` (`operation_key`),
  UNIQUE KEY `document_payments_receipt_parent_unique` (`receipt_parent_id`),
  -- ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
  PRIMARY KEY (`id`),
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `contracts` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `seller_id` int(10) unsigned DEFAULT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `prefix` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `date_of_due` date DEFAULT NULL
 * - `delivery_date` date DEFAULT NULL
 * - `customer_id` int(10) unsigned NOT NULL
 * - `customer` json NOT NULL
 * - `shipping_address` text COLLATE utf8mb4_unicode_ci
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `exchange_rate_sale` decimal(13,3) NOT NULL
 * - `total_prepayment` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_charge` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_exportation` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_free` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_taxed` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_unaffected` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_exonerated` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_igv` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_base_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_taxes` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_value` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total` decimal(12,2) NOT NULL
 * - `charges` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `prepayments` json DEFAULT NULL
 * - `guides` json DEFAULT NULL
 * - `related` json DEFAULT NULL
 * - `perception` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `terms_condition` text COLLATE utf8mb4_unicode_ci
 * - `account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `changed` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0'
 * - `quotation_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `contracts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `date_of_due` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exchange_rate_sale` decimal(13,3) NOT NULL,
  `total_prepayment` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_exportation` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_free` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_taxed` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_unaffected` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_exonerated` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_igv` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_base_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `charges` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `guides` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_condition` text COLLATE utf8mb4_unicode_ci,
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `quotation_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contracts_user_id_foreign` (`user_id`),
  KEY `contracts_establishment_id_foreign` (`establishment_id`),
  KEY `contracts_customer_id_foreign` (`customer_id`),
  KEY `contracts_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `contracts_currency_type_id_foreign` (`currency_type_id`),
  KEY `contracts_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `contracts_quotation_id_foreign` (`quotation_id`),
  KEY `contracts_state_type_id_foreign` (`state_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `contracts`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

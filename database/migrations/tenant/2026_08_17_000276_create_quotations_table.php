<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `quotations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `seller_id` int(10) unsigned DEFAULT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `prefix` char(3) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `date_of_due` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `delivery_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `customer_id` int(10) unsigned NOT NULL
 * - `customer` json NOT NULL
 * - `shipping_address` text COLLATE utf8mb4_unicode_ci
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
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
 * - `total_igv_free` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_base_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_taxes` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_value` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `custom_fields_data` json DEFAULT NULL
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
 * - `referential_information` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin'
 * - `account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `changed` tinyint(1) NOT NULL DEFAULT '0'
 * - `sale_opportunity_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `document_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
 * - `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
 * - `number` int(11) NOT NULL DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `quotations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `date_of_due` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `total_igv_free` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_base_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `custom_fields_data` json DEFAULT NULL,
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
  `referential_information` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed` tinyint(1) NOT NULL DEFAULT '0',
  `sale_opportunity_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `number` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `quotations_user_id_foreign` (`user_id`),
  KEY `quotations_establishment_id_foreign` (`establishment_id`),
  KEY `quotations_customer_id_foreign` (`customer_id`),
  KEY `quotations_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `quotations_state_type_id_foreign` (`state_type_id`),
  KEY `quotations_currency_type_id_foreign` (`currency_type_id`),
  KEY `quotations_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `quotations_sale_opportunity_id_foreign` (`sale_opportunity_id`),
  KEY `quotations_source_index` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `quotations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

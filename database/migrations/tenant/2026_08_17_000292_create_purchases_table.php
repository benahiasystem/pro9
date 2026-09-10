<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `purchases` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `group_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` int(11) NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `date_of_due` date DEFAULT NULL
 * - `time_of_issue` time NOT NULL
 * - `supplier_id` int(10) unsigned NOT NULL
 * - `supplier` json NOT NULL
 * - `purchase_order_id` int(10) unsigned DEFAULT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_condition_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `observation` text COLLATE utf8mb4_unicode_ci
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
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `total_canceled` tinyint(1) NOT NULL DEFAULT '0'
 * - `customer_id` int(10) unsigned DEFAULT NULL
 * - `perception_date` date DEFAULT NULL
 * - `perception_number` int(11) DEFAULT NULL
 * - `total_perception` decimal(12,2) DEFAULT NULL
 * - `charges` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `prepayments` json DEFAULT NULL
 * - `guides` json DEFAULT NULL
 * - `related` json DEFAULT NULL
 * - `perception` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `deleted_at` timestamp NULL DEFAULT NULL
 * - `period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_registration` datetime DEFAULT NULL
 * - `related_invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `related_invoice_serie` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `affectation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `purchases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `date_of_due` date DEFAULT NULL,
  `time_of_issue` time NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `supplier` json NOT NULL,
  `purchase_order_id` int(10) unsigned DEFAULT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_condition_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
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
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `customer_id` int(10) unsigned DEFAULT NULL,
  `perception_date` date DEFAULT NULL,
  `perception_number` int(11) DEFAULT NULL,
  `total_perception` decimal(12,2) DEFAULT NULL,
  `charges` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `guides` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_registration` datetime DEFAULT NULL,
  `related_invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_invoice_serie` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `affectation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchases_user_id_foreign` (`user_id`),
  KEY `purchases_establishment_id_foreign` (`establishment_id`),
  KEY `purchases_supplier_id_foreign` (`supplier_id`),
  KEY `purchases_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `purchases_state_type_id_foreign` (`state_type_id`),
  KEY `purchases_group_id_foreign` (`group_id`),
  KEY `purchases_document_type_id_foreign` (`document_type_id`),
  KEY `purchases_currency_type_id_foreign` (`currency_type_id`),
  KEY `purchases_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchases_customer_id_foreign` (`customer_id`),
  KEY `purchases_payment_condition_id_foreign` (`payment_condition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `purchases`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

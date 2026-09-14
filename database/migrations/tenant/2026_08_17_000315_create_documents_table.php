<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `documents` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `fiscal_emission_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `group_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` bigint unsigned NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `customer_id` int(10) unsigned NOT NULL
 * - `customer` json NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_condition_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `purchase_order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `quotation_id` int(10) unsigned DEFAULT NULL
 * - `sale_note_id` int(10) unsigned DEFAULT NULL
 * - `user_rel_suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones'
 * - `technical_service_id` int(10) unsigned DEFAULT NULL
 * - `order_note_id` int(10) unsigned DEFAULT NULL
 * - `dispatch_id` int(10) unsigned DEFAULT NULL
 * - `seller_id` int(10) unsigned DEFAULT NULL
 * - `exchange_rate_sale` decimal(13,3) NOT NULL
 * - `point_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'indica si el documento se uso en sistema por puntos'
 * - `point_system_data` json DEFAULT NULL COMMENT 'datos de sistema por puntos'
 * - `automatic_date_of_issue` date DEFAULT NULL
 * - `type_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `quantity_period` int(11) DEFAULT NULL
 * - `enabled_concurrency` tinyint(3) unsigned DEFAULT '0'
 * - `apply_concurrency` tinyint(3) unsigned DEFAULT '0'
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
 * - `total` decimal(12,2) NOT NULL
 * - `dispatch_ticket_pdf` tinyint(1) NOT NULL DEFAULT '0'
 * - `has_prepayment` tinyint(1) NOT NULL DEFAULT '0'
 * - `affectation_type_prepayment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `was_deducted_prepayment` tinyint(1) NOT NULL DEFAULT '0'
 * - `pending_amount_prepayment` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `total_pending_payment` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `charges` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `prepayments` json DEFAULT NULL
 * - `guides` json DEFAULT NULL
 * - `related` json DEFAULT NULL
 * - `perception` json DEFAULT NULL
 * - `retention` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `additional_information` text COLLATE utf8mb4_unicode_ci
 * - `additional_data` json DEFAULT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unique_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference_data` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `agent_id` int(10) unsigned DEFAULT NULL
 * - `has_pdf` tinyint(1) NOT NULL DEFAULT '0'
 * - `data_json` json DEFAULT NULL
 * - `total_canceled` tinyint(1) NOT NULL DEFAULT '0'
 * - `sale_notes_relateds` json DEFAULT NULL COMMENT 'registros asociados cuando se genera cpe desde multiples notas de venta'
 * - `terms_condition` text COLLATE utf8mb4_unicode_ci
 * - `folio` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `is_editable` tinyint(1) NOT NULL DEFAULT '0'
 * - `grade` text COLLATE utf8mb4_unicode_ci COMMENT 'Grado designado - utilizado en matricula'
 * - `section` text COLLATE utf8mb4_unicode_ci COMMENT 'Seccion designado - utilizado en matricula'
 * - `hotel_data_persons` json DEFAULT NULL COMMENT 'datos de personas - utilizado en hotel'
 * - `source_module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'origen del documento - hotel'
 * - `hotel_rent_id` int(10) unsigned DEFAULT NULL COMMENT 'referencia donde se genera el documento - hotel'
 * - `itinerant` json DEFAULT NULL
 * - `consigned_id` int(10) unsigned DEFAULT NULL
 * - `consigned_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `consigned_ubigeo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `custom_fields_data` json DEFAULT NULL
 * - `user_rel_subscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones'
 * - `collect_api_state_id` int(10) unsigned NOT NULL DEFAULT '99' COMMENT 'estado de api en global factoring'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_emission_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  -- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
  `series` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` bigint unsigned NOT NULL,
  -- ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_condition_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotation_id` int(10) unsigned DEFAULT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `user_rel_suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones',
  `technical_service_id` int(10) unsigned DEFAULT NULL,
  `order_note_id` int(10) unsigned DEFAULT NULL,
  `dispatch_id` int(10) unsigned DEFAULT NULL,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `exchange_rate_sale` decimal(13,3) NOT NULL,
  `point_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'indica si el documento se uso en sistema por puntos',
  `point_system_data` json DEFAULT NULL COMMENT 'datos de sistema por puntos',
  `automatic_date_of_issue` date DEFAULT NULL,
  `type_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_period` int(11) DEFAULT NULL,
  `enabled_concurrency` tinyint(3) unsigned DEFAULT '0',
  `apply_concurrency` tinyint(3) unsigned DEFAULT '0',
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
  `total` decimal(12,2) NOT NULL,
  `dispatch_ticket_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `has_prepayment` tinyint(1) NOT NULL DEFAULT '0',
  `affectation_type_prepayment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `was_deducted_prepayment` tinyint(1) NOT NULL DEFAULT '0',
  `pending_amount_prepayment` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_pending_payment` decimal(12,2) NOT NULL DEFAULT '0.00',
  `charges` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `guides` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `retention` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `additional_information` text COLLATE utf8mb4_unicode_ci,
  `additional_data` json DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_data` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agent_id` int(10) unsigned DEFAULT NULL,
  `has_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `data_json` json DEFAULT NULL,
  `total_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `sale_notes_relateds` json DEFAULT NULL COMMENT 'registros asociados cuando se genera cpe desde multiples notas de venta',
  `terms_condition` text COLLATE utf8mb4_unicode_ci,
  `folio` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT '0',
  `grade` text COLLATE utf8mb4_unicode_ci COMMENT 'Grado designado - utilizado en matricula',
  `section` text COLLATE utf8mb4_unicode_ci COMMENT 'Seccion designado - utilizado en matricula',
  `hotel_data_persons` json DEFAULT NULL COMMENT 'datos de personas - utilizado en hotel',
  `source_module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'origen del documento - hotel',
  `hotel_rent_id` int(10) unsigned DEFAULT NULL COMMENT 'referencia donde se genera el documento - hotel',
  `itinerant` json DEFAULT NULL,
  `consigned_id` int(10) unsigned DEFAULT NULL,
  `consigned_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consigned_ubigeo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_fields_data` json DEFAULT NULL,
  `user_rel_subscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones',
  `collect_api_state_id` int(10) unsigned NOT NULL DEFAULT '99' COMMENT 'estado de api en global factoring',
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_unique_filename_unique` (`unique_filename`),
  KEY `documents_user_id_foreign` (`user_id`),
  KEY `documents_establishment_id_foreign` (`establishment_id`),
  KEY `documents_customer_id_foreign` (`customer_id`),
  KEY `documents_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `documents_state_type_id_foreign` (`state_type_id`),
  KEY `documents_group_id_foreign` (`group_id`),
  KEY `documents_document_type_id_foreign` (`document_type_id`),
  KEY `documents_currency_type_id_foreign` (`currency_type_id`),
  KEY `documents_quotation_id_foreign` (`quotation_id`),
  KEY `documents_external_id_index` (`external_id`),
  KEY `documents_sale_note_id_foreign` (`sale_note_id`),
  KEY `documents_series_index` (`series`),
  KEY `documents_number_index` (`number`),
  KEY `documents_date_of_issue_index` (`date_of_issue`),
  KEY `documents_order_note_id_foreign` (`order_note_id`),
  KEY `documents_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `documents_seller_id_foreign` (`seller_id`),
  KEY `documents_payment_condition_id_foreign` (`payment_condition_id`),
  KEY `documents_dispatch_id_foreign` (`dispatch_id`),
  KEY `documents_technical_service_id_foreign` (`technical_service_id`),
  KEY `documents_type_period_index` (`type_period`),
  KEY `documents_agent_id_foreign` (`agent_id`),
  KEY `documents_hotel_rent_id_foreign` (`hotel_rent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documents`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

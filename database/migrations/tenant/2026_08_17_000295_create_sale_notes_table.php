<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `sale_notes` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `prefix` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `number` int(11) DEFAULT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `customer_id` int(10) unsigned NOT NULL
 * - `customer` json NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `exchange_rate_sale` decimal(13,3) NOT NULL
 * - `point_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'indica si se uso en sistema por puntos'
 * - `point_system_data` json DEFAULT NULL COMMENT 'datos de sistema por puntos'
 * - `created_from_pos` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'indica si se registro desde pos'
 * - `apply_concurrency` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_concurrency` tinyint(1) NOT NULL DEFAULT '0'
 * - `automatic_date_of_issue` date DEFAULT NULL
 * - `quantity_period` int(11) DEFAULT NULL
 * - `type_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
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
 * - `charges` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `prepayments` json DEFAULT NULL
 * - `guides` json DEFAULT NULL
 * - `related` json DEFAULT NULL
 * - `perception` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `additional_information` text COLLATE utf8mb4_unicode_ci
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unique_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `quotation_id` int(10) unsigned DEFAULT NULL
 * - `order_note_id` int(10) unsigned DEFAULT NULL
 * - `technical_service_id` int(10) unsigned DEFAULT NULL
 * - `order_id` int(10) unsigned DEFAULT NULL
 * - `total_canceled` tinyint(1) NOT NULL DEFAULT '0'
 * - `changed` tinyint(1) NOT NULL DEFAULT '0'
 * - `paid` tinyint(1) NOT NULL DEFAULT '0'
 * - `license_plate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference_data` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `agent_id` int(10) unsigned DEFAULT NULL
 * - `observation` text COLLATE utf8mb4_unicode_ci
 * - `purchase_order` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `document_id` int(10) unsigned DEFAULT NULL
 * - `user_rel_suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones'
 * - `due_date` date DEFAULT NULL COMMENT 'Fecha de vencimiento'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `seller_id` int(10) unsigned DEFAULT '0'
 * - `grade` text COLLATE utf8mb4_unicode_ci COMMENT 'Grado designado - utilizado en matricula'
 * - `section` text COLLATE utf8mb4_unicode_ci COMMENT 'Seccion designado - utilizado en matricula'
 * - `terms_condition` text COLLATE utf8mb4_unicode_ci
 * - `hotel_data_persons` json DEFAULT NULL COMMENT 'datos de personas - utilizado en hotel'
 * - `source_module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'origen del documento - hotel'
 * - `hotel_rent_id` int(10) unsigned DEFAULT NULL COMMENT 'referencia donde se genera el documento - hotel'
 * - `payment_condition_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `consigned_id` int(10) unsigned DEFAULT NULL
 * - `consigned_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `consigned_ubigeo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `custom_fields_data` json DEFAULT NULL
 * - `document_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
 * - `user_rel_subscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones'
 * - `voided_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `sale_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` int(11) DEFAULT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_rate_sale` decimal(13,3) NOT NULL,
  `point_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'indica si se uso en sistema por puntos',
  `point_system_data` json DEFAULT NULL COMMENT 'datos de sistema por puntos',
  `created_from_pos` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'indica si se registro desde pos',
  `apply_concurrency` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_concurrency` tinyint(1) NOT NULL DEFAULT '0',
  `automatic_date_of_issue` date DEFAULT NULL,
  `quantity_period` int(11) DEFAULT NULL,
  `type_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `charges` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `guides` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `additional_information` text COLLATE utf8mb4_unicode_ci,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotation_id` int(10) unsigned DEFAULT NULL,
  `order_note_id` int(10) unsigned DEFAULT NULL,
  `technical_service_id` int(10) unsigned DEFAULT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `total_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `changed` tinyint(1) NOT NULL DEFAULT '0',
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  `license_plate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_data` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agent_id` int(10) unsigned DEFAULT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
  `purchase_order` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_id` int(10) unsigned DEFAULT NULL,
  `user_rel_suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones',
  `due_date` date DEFAULT NULL COMMENT 'Fecha de vencimiento',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `seller_id` int(10) unsigned DEFAULT '0',
  `grade` text COLLATE utf8mb4_unicode_ci COMMENT 'Grado designado - utilizado en matricula',
  `section` text COLLATE utf8mb4_unicode_ci COMMENT 'Seccion designado - utilizado en matricula',
  `terms_condition` text COLLATE utf8mb4_unicode_ci,
  `hotel_data_persons` json DEFAULT NULL COMMENT 'datos de personas - utilizado en hotel',
  `source_module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'origen del documento - hotel',
  `hotel_rent_id` int(10) unsigned DEFAULT NULL COMMENT 'referencia donde se genera el documento - hotel',
  `payment_condition_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consigned_id` int(10) unsigned DEFAULT NULL,
  `consigned_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consigned_ubigeo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_fields_data` json DEFAULT NULL,
  `document_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_rel_subscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con suscripciones',
  `voided_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_notes_unique_filename_unique` (`unique_filename`),
  KEY `sale_notes_user_id_foreign` (`user_id`),
  KEY `sale_notes_establishment_id_foreign` (`establishment_id`),
  KEY `sale_notes_customer_id_foreign` (`customer_id`),
  KEY `sale_notes_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `sale_notes_state_type_id_foreign` (`state_type_id`),
  KEY `sale_notes_currency_type_id_foreign` (`currency_type_id`),
  KEY `sale_notes_quotation_id_foreign` (`quotation_id`),
  KEY `sale_notes_apply_concurrency_index` (`apply_concurrency`),
  KEY `sale_notes_type_period_index` (`type_period`),
  KEY `sale_notes_quantity_period_index` (`quantity_period`),
  KEY `sale_notes_automatic_date_of_issue_index` (`automatic_date_of_issue`),
  KEY `sale_notes_enabled_concurrency_index` (`enabled_concurrency`),
  KEY `sale_notes_order_note_id_foreign` (`order_note_id`),
  KEY `sale_notes_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `sale_notes_order_id_foreign` (`order_id`),
  KEY `sale_notes_technical_service_id_foreign` (`technical_service_id`),
  KEY `sale_notes_agent_id_foreign` (`agent_id`),
  KEY `sale_notes_changed_index` (`changed`),
  KEY `sale_notes_hotel_rent_id_foreign` (`hotel_rent_id`),
  KEY `sale_notes_payment_condition_id_foreign` (`payment_condition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `sale_notes`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

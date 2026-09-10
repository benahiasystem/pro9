<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `dispatches` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `reference_sale_note_id` int(10) unsigned DEFAULT NULL
 * - `reference_document_id` int(10) unsigned DEFAULT NULL
 * - `reference_quotation_id` int(10) unsigned DEFAULT NULL
 * - `reference_order_form_id` int(10) unsigned DEFAULT NULL
 * - `reference_order_note_id` int(10) unsigned DEFAULT NULL
 * - `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` int(11) NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `date_delivery_to_transport` date DEFAULT NULL COMMENT 'para orden de entrega solo con transporte publico'
 * - `customer_id` int(10) unsigned DEFAULT NULL
 * - `buyer_id` int(10) unsigned DEFAULT NULL
 * - `buyer` json DEFAULT NULL
 * - `customer` json DEFAULT NULL
 * - `observations` text COLLATE utf8mb4_unicode_ci
 * - `transport_mode_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `transfer_reason_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `transfer_reason_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_of_shipping` date NOT NULL
 * - `transshipment_indicator` tinyint(1) NOT NULL
 * - `port_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total_weight` decimal(10,2) NOT NULL
 * - `packages_number` int(11) DEFAULT NULL
 * - `container_number` int(11) DEFAULT NULL
 * - `related` json DEFAULT NULL COMMENT 'Numero de DAM'
 * - `origin` json DEFAULT NULL
 * - `delivery` json DEFAULT NULL
 * - `has_transport_driver_01` tinyint(1) DEFAULT '0'
 * - `dispatcher_id` int(10) unsigned DEFAULT NULL
 * - `dispatcher` json DEFAULT NULL
 * - `driver_id` int(10) unsigned DEFAULT NULL
 * - `driver` json DEFAULT NULL
 * - `secondary_drivers` json DEFAULT NULL
 * - `order_form_external` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `license_plate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `sender_id` int(10) unsigned DEFAULT NULL
 * - `sender_data` json DEFAULT NULL
 * - `sender_address_id` int(10) unsigned DEFAULT NULL
 * - `sender_address_data` json DEFAULT NULL
 * - `receiver_id` int(10) unsigned DEFAULT NULL
 * - `receiver_data` json DEFAULT NULL
 * - `receiver_address_id` int(10) unsigned DEFAULT NULL
 * - `receiver_address_data` json DEFAULT NULL
 * - `transport_id` int(10) unsigned DEFAULT NULL
 * - `transport_data` json DEFAULT NULL
 * - `secondary_transports` json DEFAULT NULL
 * - `secondary_license_plates` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `payer` json DEFAULT NULL
 * - `optional` json DEFAULT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `has_pdf` tinyint(1) NOT NULL DEFAULT '0'
 * - `document_id` int(10) unsigned DEFAULT NULL
 * - `qr_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `data_affected_document` longtext COLLATE utf8mb4_unicode_ci
 * - `terms_condition` text COLLATE utf8mb4_unicode_ci
 * - `additional_data` json DEFAULT NULL
 * - `origin_address_id` int(10) unsigned DEFAULT NULL
 * - `delivery_address_id` int(10) unsigned DEFAULT NULL
 * - `is_transport_m1l` tinyint(1) DEFAULT '0'
 * - `license_plate_m1l` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference_documents` json DEFAULT NULL
 * - `custom_fields_data` json DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_sale_note_id` int(10) unsigned DEFAULT NULL,
  `reference_document_id` int(10) unsigned DEFAULT NULL,
  `reference_quotation_id` int(10) unsigned DEFAULT NULL,
  `reference_order_form_id` int(10) unsigned DEFAULT NULL,
  `reference_order_note_id` int(10) unsigned DEFAULT NULL,
  `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `date_delivery_to_transport` date DEFAULT NULL COMMENT 'para orden de entrega solo con transporte publico',
  `customer_id` int(10) unsigned DEFAULT NULL,
  `buyer_id` int(10) unsigned DEFAULT NULL,
  `buyer` json DEFAULT NULL,
  `customer` json DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `transport_mode_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_reason_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_reason_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_shipping` date NOT NULL,
  `transshipment_indicator` tinyint(1) NOT NULL,
  `port_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_weight` decimal(10,2) NOT NULL,
  `packages_number` int(11) DEFAULT NULL,
  `container_number` int(11) DEFAULT NULL,
  `related` json DEFAULT NULL COMMENT 'Numero de DAM',
  `origin` json DEFAULT NULL,
  `delivery` json DEFAULT NULL,
  `has_transport_driver_01` tinyint(1) DEFAULT '0',
  `dispatcher_id` int(10) unsigned DEFAULT NULL,
  `dispatcher` json DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `driver` json DEFAULT NULL,
  `secondary_drivers` json DEFAULT NULL,
  `order_form_external` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_plate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender_id` int(10) unsigned DEFAULT NULL,
  `sender_data` json DEFAULT NULL,
  `sender_address_id` int(10) unsigned DEFAULT NULL,
  `sender_address_data` json DEFAULT NULL,
  `receiver_id` int(10) unsigned DEFAULT NULL,
  `receiver_data` json DEFAULT NULL,
  `receiver_address_id` int(10) unsigned DEFAULT NULL,
  `receiver_address_data` json DEFAULT NULL,
  `transport_id` int(10) unsigned DEFAULT NULL,
  `transport_data` json DEFAULT NULL,
  `secondary_transports` json DEFAULT NULL,
  `secondary_license_plates` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `payer` json DEFAULT NULL,
  `optional` json DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `document_id` int(10) unsigned DEFAULT NULL,
  `qr_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `data_affected_document` longtext COLLATE utf8mb4_unicode_ci,
  `terms_condition` text COLLATE utf8mb4_unicode_ci,
  `additional_data` json DEFAULT NULL,
  `origin_address_id` int(10) unsigned DEFAULT NULL,
  `delivery_address_id` int(10) unsigned DEFAULT NULL,
  `is_transport_m1l` tinyint(1) DEFAULT '0',
  `license_plate_m1l` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_documents` json DEFAULT NULL,
  `custom_fields_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispatches_user_id_foreign` (`user_id`),
  KEY `dispatches_establishment_id_foreign` (`establishment_id`),
  KEY `dispatches_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `dispatches_state_type_id_foreign` (`state_type_id`),
  KEY `dispatches_document_type_id_foreign` (`document_type_id`),
  KEY `dispatches_customer_id_foreign` (`customer_id`),
  KEY `dispatches_unit_type_id_foreign` (`unit_type_id`),
  KEY `dispatches_transport_mode_type_id_foreign` (`transport_mode_type_id`),
  KEY `dispatches_transfer_reason_type_id_foreign` (`transfer_reason_type_id`),
  KEY `dispatches_document_id_foreign` (`document_id`),
  KEY `dispatches_dispatcher_id_foreign` (`dispatcher_id`),
  KEY `dispatches_driver_id_foreign` (`driver_id`),
  KEY `dispatches_transport_id_foreign` (`transport_id`),
  KEY `dispatches_sender_id_foreign` (`sender_id`),
  KEY `dispatches_sender_address_id_foreign` (`sender_address_id`),
  KEY `dispatches_receiver_id_foreign` (`receiver_id`),
  KEY `dispatches_receiver_address_id_foreign` (`receiver_address_id`),
  KEY `dispatches_buyer_id_foreign` (`buyer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatches`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

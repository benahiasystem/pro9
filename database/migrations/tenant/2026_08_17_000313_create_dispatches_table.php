<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `dispatches`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `external_id`: char(36); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `establishment`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `state_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `ubl_version`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reference_sale_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `reference_document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `reference_quotation_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `reference_order_form_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `reference_order_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `series`: char(4); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_of_issue`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_delivery_to_transport`: date; NULL — para guia remision solo con transporte publico
 * - `customer_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `buyer_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `buyer`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `customer`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `observations`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `transport_mode_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `transfer_reason_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `transfer_reason_description`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_of_shipping`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `transshipment_indicator`: tinyint(1); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `port_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unit_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_weight`: decimal(10,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `packages_number`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `container_number`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `related`: json; NULL — Numero de DAM
 * - `origin`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `delivery`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `has_transport_driver_01`: tinyint(1); NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `dispatcher_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `dispatcher`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `driver_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `driver`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `secondary_drivers`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `order_form_external`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `license_plate`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sender_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sender_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `sender_address_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sender_address_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `receiver_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `receiver_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `receiver_address_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `receiver_address_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `transport_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `transport_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `secondary_transports`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `secondary_license_plates`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `legends`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `payer`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `optional`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `hash`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_shipping_response`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `sunat_error_response`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `send_to_pse`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `response_signature_pse`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `response_send_cdr_pse`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `has_xml`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `has_pdf`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `has_cdr`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `ticket`: varchar(50); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reception_date`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `qr_url`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `data_affected_document`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `terms_condition`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `additional_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `origin_address_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `delivery_address_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `is_transport_m1l`: tinyint(1); NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `license_plate_m1l`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reference_documents`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `custom_fields_data`: json; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `date_delivery_to_transport` date DEFAULT NULL COMMENT 'para guia remision solo con transporte publico',
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
  `soap_shipping_response` json DEFAULT NULL,
  `sunat_error_response` json DEFAULT NULL,
  `send_to_pse` tinyint(1) NOT NULL DEFAULT '0',
  `response_signature_pse` json DEFAULT NULL,
  `response_send_cdr_pse` json DEFAULT NULL,
  `has_xml` tinyint(1) NOT NULL DEFAULT '0',
  `has_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `has_cdr` tinyint(1) NOT NULL DEFAULT '0',
  `document_id` int(10) unsigned DEFAULT NULL,
  `ticket` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reception_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `dispatches_soap_type_id_foreign` (`soap_type_id`),
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

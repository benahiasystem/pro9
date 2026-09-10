<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documents`.
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
 * - `ticket_single_shipment`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `force_send_by_summary`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `group_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `series`: char(4); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_of_issue`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_condition_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `purchase_order`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `plate_number`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quotation_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `user_rel_suscription_plan_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con suscripciones
 * - `technical_service_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `order_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `dispatch_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `seller_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `exchange_rate_sale`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `point_system`: tinyint(1); NOT NULL; DEFAULT 0 — indica si el documento se uso en sistema por puntos
 * - `point_system_data`: json; NULL — datos de sistema por puntos
 * - `automatic_date_of_issue`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `type_period`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quantity_period`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `enabled_concurrency`: tinyint(3) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `apply_concurrency`: tinyint(3) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `total_prepayment`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_charge`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_discount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_exportation`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_free`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxed`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_unaffected`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_exonerated`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv_free`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_other_taxes`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_other_taxes`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_plastic_bag_taxes`: decimal(6,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_value`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `subtotal`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `dispatch_ticket_pdf`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `has_prepayment`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `affectation_type_prepayment`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `was_deducted_prepayment`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `pending_amount_prepayment`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_pending_payment`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `charges`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `discounts`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `prepayments`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `guides`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `related`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `perception`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `retention`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `legends`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `additional_information`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `additional_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unique_filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `hash`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `qr`: longtext; NULL; COLLATE utf8mb4_unicode_ci —
 * - `reference_data`: varchar(500); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `agent_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `has_xml`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `has_pdf`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `has_cdr`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `data_json`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `send_server`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `success_shipping_status`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `shipping_status`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `success_sunat_shipping_status`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `sunat_shipping_status`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `query_status`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `success_query_status`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `total_canceled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `soap_shipping_response`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `regularize_shipping`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `response_regularize_shipping`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `send_to_pse`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `response_signature_pse`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `response_send_cdr_pse`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `sale_notes_relateds`: json; NULL — registros asociados cuando se genera cpe desde multiples notas de venta
 * - `terms_condition`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `folio`: varchar(50); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `is_editable`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `grade`: text; NULL; COLLATE utf8mb4_unicode_ci — Grado designado - utilizado en matricula
 * - `section`: text; NULL; COLLATE utf8mb4_unicode_ci — Seccion designado - utilizado en matricula
 * - `hotel_data_persons`: json; NULL — datos de personas - utilizado en hotel
 * - `source_module`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — origen del documento - hotel
 * - `hotel_rent_id`: int(10) unsigned; NULL — referencia donde se genera el documento - hotel
 * - `itinerant`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `consigned_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `consigned_address`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `consigned_ubigeo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `custom_fields_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `user_rel_subscription_plan_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con suscripciones
 * - `collect_api_state_id`: int(10) unsigned; NOT NULL; DEFAULT 99 — estado de api en global factoring
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_single_shipment` tinyint(1) NOT NULL DEFAULT '0',
  `force_send_by_summary` tinyint(1) NOT NULL DEFAULT '0',
  `group_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
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
  `total_base_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_base_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_plastic_bag_taxes` decimal(6,2) NOT NULL DEFAULT '0.00',
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
  `hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr` longtext COLLATE utf8mb4_unicode_ci COMMENT ' ',
  `reference_data` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agent_id` int(10) unsigned DEFAULT NULL,
  `has_xml` tinyint(1) NOT NULL DEFAULT '0',
  `has_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `has_cdr` tinyint(1) NOT NULL DEFAULT '0',
  `data_json` json DEFAULT NULL,
  `send_server` tinyint(1) NOT NULL DEFAULT '1',
  `success_shipping_status` tinyint(1) NOT NULL DEFAULT '0',
  `shipping_status` json DEFAULT NULL,
  `success_sunat_shipping_status` tinyint(1) NOT NULL DEFAULT '0',
  `sunat_shipping_status` json DEFAULT NULL,
  `query_status` json DEFAULT NULL,
  `success_query_status` tinyint(1) NOT NULL DEFAULT '0',
  `total_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `soap_shipping_response` json DEFAULT NULL,
  `regularize_shipping` tinyint(1) NOT NULL DEFAULT '0',
  `response_regularize_shipping` json DEFAULT NULL,
  `send_to_pse` tinyint(1) NOT NULL DEFAULT '0',
  `response_signature_pse` json DEFAULT NULL,
  `response_send_cdr_pse` json DEFAULT NULL,
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
  KEY `documents_soap_type_id_foreign` (`soap_type_id`),
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
  KEY `documents_regularize_shipping_index` (`regularize_shipping`),
  KEY `documents_ticket_single_shipment_index` (`ticket_single_shipment`),
  KEY `documents_agent_id_foreign` (`agent_id`),
  KEY `documents_force_send_by_summary_index` (`force_send_by_summary`),
  KEY `documents_hotel_rent_id_foreign` (`hotel_rent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documents`');
    }
};
// ######### FIN CAMBIO NELSON #########

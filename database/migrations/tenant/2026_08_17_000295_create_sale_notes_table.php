<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `sale_notes`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `external_id`: char(36); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `establishment`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `state_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `prefix`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `series`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_of_issue`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `exchange_rate_sale`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `point_system`: tinyint(1); NOT NULL; DEFAULT 0 — indica si se uso en sistema por puntos
 * - `point_system_data`: json; NULL — datos de sistema por puntos
 * - `created_from_pos`: tinyint(1); NOT NULL; DEFAULT 0 — indica si se registro desde pos
 * - `apply_concurrency`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_concurrency`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `automatic_date_of_issue`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `quantity_period`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `type_period`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
 * - `charges`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `discounts`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `prepayments`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `guides`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `related`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `perception`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `detraction`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `legends`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `additional_information`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unique_filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quotation_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `order_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `technical_service_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `order_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `total_canceled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `changed`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `paid`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `license_plate`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `plate_number`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reference_data`: varchar(500); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `agent_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `observation`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `purchase_order`: varchar(50); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `user_rel_suscription_plan_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con suscripciones
 * - `due_date`: date; NULL — Fecha de vencimiento
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `seller_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `grade`: text; NULL; COLLATE utf8mb4_unicode_ci — Grado designado - utilizado en matricula
 * - `section`: text; NULL; COLLATE utf8mb4_unicode_ci — Seccion designado - utilizado en matricula
 * - `terms_condition`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `hotel_data_persons`: json; NULL — datos de personas - utilizado en hotel
 * - `source_module`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — origen del documento - hotel
 * - `hotel_rent_id`: int(10) unsigned; NULL — referencia donde se genera el documento - hotel
 * - `payment_condition_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `consigned_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `consigned_address`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `consigned_ubigeo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `custom_fields_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: char(2); NOT NULL; DEFAULT ; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `user_rel_subscription_plan_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con suscripciones
 * - `voided_description`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `charges` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `guides` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `detraction` json DEFAULT NULL,
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
  KEY `sale_notes_soap_type_id_foreign` (`soap_type_id`),
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `sale_notes`');
    }
};
// ######### FIN CAMBIO NELSON #########

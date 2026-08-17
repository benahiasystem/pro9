<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `technical_services`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `establishment`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `customer_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_condition_id`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `seller_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `exchange_rate_sale`: decimal(13,3); NULL; DEFAULT 0.000 — Sin comentario definido en el esquema fuente.
 * - `total_prepayment`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_charge`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_discount`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_exportation`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_free`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxed`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_unaffected`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_exonerated`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv_free`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_isc`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_isc`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_other_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_other_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_plastic_bag_taxes`: decimal(6,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_value`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `subtotal`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `is_editable`: tinyint(3) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `cellphone`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_of_issue`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `description`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `state`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reason`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `serial_number`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `cost`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `prepayment`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `activities`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `brand`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `equipment`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `important_note`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `repair`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `warranty`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `maintenance`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `diagnosis`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `technical_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned DEFAULT '0',
  `establishment` json DEFAULT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `currency_type_id` text COLLATE utf8mb4_unicode_ci,
  `payment_condition_id` text COLLATE utf8mb4_unicode_ci,
  `payment_method_type_id` text COLLATE utf8mb4_unicode_ci,
  `seller_id` int(10) unsigned DEFAULT '0',
  `exchange_rate_sale` decimal(13,3) DEFAULT '0.000',
  `total_prepayment` decimal(12,2) DEFAULT '0.00',
  `total_charge` decimal(12,2) DEFAULT '0.00',
  `total_discount` decimal(12,2) DEFAULT '0.00',
  `total_exportation` decimal(12,2) DEFAULT '0.00',
  `total_free` decimal(12,2) DEFAULT '0.00',
  `total_taxed` decimal(12,2) DEFAULT '0.00',
  `total_unaffected` decimal(12,2) DEFAULT '0.00',
  `total_exonerated` decimal(12,2) DEFAULT '0.00',
  `total_igv` decimal(12,2) DEFAULT '0.00',
  `total_igv_free` decimal(12,2) DEFAULT '0.00',
  `total_base_isc` decimal(12,2) DEFAULT '0.00',
  `total_isc` decimal(12,2) DEFAULT '0.00',
  `total_base_other_taxes` decimal(12,2) DEFAULT '0.00',
  `total_other_taxes` decimal(12,2) DEFAULT '0.00',
  `total_plastic_bag_taxes` decimal(6,2) DEFAULT '0.00',
  `total_taxes` decimal(12,2) DEFAULT '0.00',
  `total_value` decimal(12,2) DEFAULT '0.00',
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `is_editable` tinyint(3) unsigned DEFAULT '0',
  `cellphone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `prepayment` decimal(12,2) NOT NULL DEFAULT '0.00',
  `activities` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `important_note` text COLLATE utf8mb4_unicode_ci,
  `repair` tinyint(1) NOT NULL DEFAULT '0',
  `warranty` tinyint(1) NOT NULL DEFAULT '0',
  `maintenance` tinyint(1) NOT NULL DEFAULT '0',
  `diagnosis` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `technical_services_user_id_foreign` (`user_id`),
  KEY `technical_services_soap_type_id_foreign` (`soap_type_id`),
  KEY `technical_services_customer_id_foreign` (`customer_id`),
  KEY `technical_services_date_of_issue_index` (`date_of_issue`),
  KEY `technical_services_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `technical_services`');
    }
};

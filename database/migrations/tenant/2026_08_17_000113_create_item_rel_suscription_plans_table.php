<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `item_rel_suscription_plans`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con items
 * - `item`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `suscription_plan_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con planes de suscripcion
 * - `quantity`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `unit_value`: decimal(16,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `affectation_igv_type_id`: char(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_base_igv`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_igv`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `system_isc_type_id`: char(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_base_isc`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_isc`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_isc`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_other_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_other_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_other_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `price_type_id`: char(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unit_price`: decimal(16,6); NULL; DEFAULT 0.000000 — Sin comentario definido en el esquema fuente.
 * - `total_value`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_charge`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_discount`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `attributes`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `discounts`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `charges`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `additional_information`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `warehouse_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `apply_in_period`: varchar(255); NOT NULL; DEFAULT all; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name_product_pdf`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_rel_suscription_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con items',
  `item` json DEFAULT NULL,
  `suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con planes de suscripcion',
  `quantity` decimal(12,2) DEFAULT '0.00',
  `unit_value` decimal(16,2) DEFAULT '0.00',
  `affectation_igv_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_base_igv` decimal(12,2) DEFAULT '0.00',
  `percentage_igv` decimal(12,2) DEFAULT '0.00',
  `total_igv` decimal(12,2) DEFAULT '0.00',
  `system_isc_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_base_isc` decimal(12,2) DEFAULT '0.00',
  `percentage_isc` decimal(12,2) DEFAULT '0.00',
  `total_isc` decimal(12,2) DEFAULT '0.00',
  `total_base_other_taxes` decimal(12,2) DEFAULT '0.00',
  `percentage_other_taxes` decimal(12,2) DEFAULT '0.00',
  `total_other_taxes` decimal(12,2) DEFAULT '0.00',
  `total_taxes` decimal(12,2) DEFAULT '0.00',
  `price_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(16,6) DEFAULT '0.000000',
  `total_value` decimal(12,2) DEFAULT '0.00',
  `total_charge` decimal(12,2) DEFAULT '0.00',
  `total_discount` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `attributes` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `charges` json DEFAULT NULL,
  `additional_information` text COLLATE utf8mb4_unicode_ci,
  `warehouse_id` int(10) unsigned DEFAULT '0',
  `apply_in_period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_rel_suscription_plans`');
    }
};

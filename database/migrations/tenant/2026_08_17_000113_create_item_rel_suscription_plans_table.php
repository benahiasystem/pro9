<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `item_rel_suscription_plans` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con items'
 * - `item` json DEFAULT NULL
 * - `suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con planes de suscripcion'
 * - `quantity` decimal(30,12) DEFAULT '0.00'
 * - `unit_value` decimal(30,12) DEFAULT '0.00'
 * - `affectation_igv_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `total_base_igv` decimal(30,12) DEFAULT '0.00'
 * - `percentage_igv` decimal(30,12) DEFAULT '0.00'
 * - `total_igv` decimal(30,12) DEFAULT '0.00'
 * - `total_base_other_taxes` decimal(30,12) DEFAULT '0.00'
 * - `percentage_other_taxes` decimal(30,12) DEFAULT '0.00'
 * - `total_other_taxes` decimal(30,12) DEFAULT '0.00'
 * - `total_taxes` decimal(30,12) DEFAULT '0.00'
 * - `price_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unit_price` decimal(30,12) DEFAULT '0.000000'
 * - `total_value` decimal(30,12) DEFAULT '0.00'
 * - `total_charge` decimal(30,12) DEFAULT '0.00'
 * - `total_discount` decimal(30,12) DEFAULT '0.00'
 * - `total` decimal(30,12) DEFAULT '0.00'
 * - `attributes` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `charges` json DEFAULT NULL
 * - `additional_information` text COLLATE utf8mb4_unicode_ci
 * - `warehouse_id` int(10) unsigned DEFAULT '0'
 * - `apply_in_period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all'
 * - `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
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
  `quantity` decimal(30,12) DEFAULT '0.00',
  `unit_value` decimal(30,12) DEFAULT '0.00',
  `affectation_igv_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_base_igv` decimal(30,12) DEFAULT '0.00',
  `percentage_igv` decimal(30,12) DEFAULT '0.00',
  `total_igv` decimal(30,12) DEFAULT '0.00',
  `total_base_other_taxes` decimal(30,12) DEFAULT '0.00',
  `percentage_other_taxes` decimal(30,12) DEFAULT '0.00',
  `total_other_taxes` decimal(30,12) DEFAULT '0.00',
  `total_taxes` decimal(30,12) DEFAULT '0.00',
  `price_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(30,12) DEFAULT '0.000000',
  `total_value` decimal(30,12) DEFAULT '0.00',
  `total_charge` decimal(30,12) DEFAULT '0.00',
  `total_discount` decimal(30,12) DEFAULT '0.00',
  `total` decimal(30,12) DEFAULT '0.00',
  `attributes` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `charges` json DEFAULT NULL,
  `additional_information` text COLLATE utf8mb4_unicode_ci,
  `warehouse_id` int(10) unsigned DEFAULT '0',
  `apply_in_period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_rel_suscription_plans`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `suscription_plans` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `cat_period_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con el periodo de tiempo'
 * - `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del plan'
 * - `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripcion del plan'
 * - `total` decimal(30,12) DEFAULT '0.00' COMMENT 'El total del costo del plan'
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment_method_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `quantity_period` int(10) unsigned DEFAULT NULL
 * - `unlimited` tinyint(1) NOT NULL DEFAULT '0'
 * - `trial_days` int(11) DEFAULT NULL
 * - `status` tinyint(1) NOT NULL DEFAULT '1'
 * - `exchange_rate_sale` decimal(18,8) DEFAULT '0.000'
 * - `total_prepayment` decimal(30,12) DEFAULT '0.00'
 * - `total_charge` decimal(30,12) DEFAULT '0.00'
 * - `total_discount` decimal(30,12) DEFAULT '0.00'
 * - `total_exportation` decimal(30,12) DEFAULT '0.00'
 * - `total_free` decimal(30,12) DEFAULT '0.00'
 * - `total_taxed` decimal(30,12) DEFAULT '0.00'
 * - `total_unaffected` decimal(30,12) DEFAULT '0.00'
 * - `total_exonerated` decimal(30,12) DEFAULT '0.00'
 * - `total_igv` decimal(30,12) DEFAULT '0.00'
 * - `total_igv_free` decimal(30,12) DEFAULT '0.00'
 * - `total_base_other_taxes` decimal(30,12) DEFAULT '0.00'
 * - `total_other_taxes` decimal(30,12) DEFAULT '0.00'
 * - `total_taxes` decimal(30,12) DEFAULT '0.00'
 * - `total_value` decimal(30,12) DEFAULT '0.00'
 * - `charges` json DEFAULT NULL
 * - `attributes` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `prepayments` json DEFAULT NULL
 * - `related` json DEFAULT NULL
 * - `perception` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `terms_condition` longtext COLLATE utf8mb4_unicode_ci
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `suscription_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cat_period_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con el periodo de tiempo',
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del plan',
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripcion del plan',
  `total` decimal(30,12) DEFAULT '0.00' COMMENT 'El total del costo del plan',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_period` int(10) unsigned DEFAULT NULL,
  `unlimited` tinyint(1) NOT NULL DEFAULT '0',
  `trial_days` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `exchange_rate_sale` decimal(18,8) DEFAULT '0.000',
  `total_prepayment` decimal(30,12) DEFAULT '0.00',
  `total_charge` decimal(30,12) DEFAULT '0.00',
  `total_discount` decimal(30,12) DEFAULT '0.00',
  `total_exportation` decimal(30,12) DEFAULT '0.00',
  `total_free` decimal(30,12) DEFAULT '0.00',
  `total_taxed` decimal(30,12) DEFAULT '0.00',
  `total_unaffected` decimal(30,12) DEFAULT '0.00',
  `total_exonerated` decimal(30,12) DEFAULT '0.00',
  `total_igv` decimal(30,12) DEFAULT '0.00',
  `total_igv_free` decimal(30,12) DEFAULT '0.00',
  `total_base_other_taxes` decimal(30,12) DEFAULT '0.00',
  `total_other_taxes` decimal(30,12) DEFAULT '0.00',
  `total_taxes` decimal(30,12) DEFAULT '0.00',
  `total_value` decimal(30,12) DEFAULT '0.00',
  `charges` json DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `terms_condition` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `suscription_plans`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

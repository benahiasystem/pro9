<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `user_rel_suscription_plans` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con usuario'
 * - `suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con planes de suscripcion'
 * - `cat_period_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con el periodo de tiempo'
 * - `items` json NOT NULL COMMENT 'Pega los items relacionados a modo de standar'
 * - `editable` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si ya ha sido adquirido, no puede ser modificado'
 * - `deletable` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si ya ha sido adquirido, no puede ser borrado'
 * - `start_date` date DEFAULT NULL COMMENT 'Fecha de inicio'
 * - `subscription_status` enum('authorized','paused','cancelled','finished') COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `no_send_link` tinyint(1) NOT NULL DEFAULT '0'
 * - `total_send_link` int(11) NOT NULL DEFAULT '0'
 * - `trial_start_date` date DEFAULT NULL
 * - `trial_days` int(11) DEFAULT NULL
 * - `sale_notes` text COLLATE utf8mb4_unicode_ci
 * - `documents` text COLLATE utf8mb4_unicode_ci
 * - `dates_of_documents` text COLLATE utf8mb4_unicode_ci
 * - `automatic_date_of_issue` date DEFAULT NULL
 * - `customer_id` int(10) unsigned DEFAULT '0'
 * - `customer` json NOT NULL
 * - `parent_customer_id` int(10) unsigned DEFAULT '0'
 * - `parent_customer` json NOT NULL
 * - `children_customer_id` int(10) unsigned DEFAULT '0'
 * - `children_customer` longtext COLLATE utf8mb4_unicode_ci
 * - `quantity_period` int(10) unsigned DEFAULT '0'
 * - `apply_concurrency` int(10) unsigned NOT NULL DEFAULT '0'
 * - `enabled_concurrency` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'Se pasará a nota de ventas como activo'
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment_method_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `exchange_rate_sale` double(13,3) DEFAULT '0.000'
 * - `total_prepayment` double(12,2) DEFAULT '0.00'
 * - `total_charge` double(12,2) DEFAULT '0.00'
 * - `total_discount` double(12,2) DEFAULT '0.00'
 * - `total_exportation` double(12,2) DEFAULT '0.00'
 * - `total_free` double(12,2) DEFAULT '0.00'
 * - `total_taxed` double(12,2) DEFAULT '0.00'
 * - `total_unaffected` double(12,2) DEFAULT '0.00'
 * - `total_exonerated` double(12,2) DEFAULT '0.00'
 * - `total_igv` double(12,2) DEFAULT '0.00'
 * - `total_igv_free` double(12,2) DEFAULT '0.00'
 * - `total_base_other_taxes` double(12,2) DEFAULT '0.00'
 * - `total_other_taxes` double(12,2) DEFAULT '0.00'
 * - `total_taxes` double(12,2) DEFAULT '0.00'
 * - `total_value` double(12,2) DEFAULT '0.00'
 * - `total` decimal(12,2) DEFAULT '0.00'
 * - `charges` json DEFAULT NULL
 * - `attributes` json DEFAULT NULL
 * - `discounts` json DEFAULT NULL
 * - `prepayments` json DEFAULT NULL
 * - `related` json DEFAULT NULL
 * - `perception` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `terms_condition` longtext COLLATE utf8mb4_unicode_ci
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `grade` text COLLATE utf8mb4_unicode_ci COMMENT 'Grado designado - utilizado en matricula'
 * - `section` text COLLATE utf8mb4_unicode_ci COMMENT 'Seccion designado - utilizado en matricula'
 * - `orders_created` int(11) NOT NULL DEFAULT '0' COMMENT 'Cantidad de órdenes de cobro creadas para esta suscripción'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `user_rel_suscription_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con usuario',
  `suscription_plan_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con planes de suscripcion',
  `cat_period_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con el periodo de tiempo',
  `items` json NOT NULL COMMENT 'Pega los items relacionados a modo de standar',
  `editable` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si ya ha sido adquirido, no puede ser modificado',
  `deletable` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si ya ha sido adquirido, no puede ser borrado',
  `start_date` date DEFAULT NULL COMMENT 'Fecha de inicio',
  `subscription_status` enum('authorized','paused','cancelled','finished') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_send_link` tinyint(1) NOT NULL DEFAULT '0',
  `total_send_link` int(11) NOT NULL DEFAULT '0',
  `trial_start_date` date DEFAULT NULL,
  `trial_days` int(11) DEFAULT NULL,
  `sale_notes` text COLLATE utf8mb4_unicode_ci,
  `documents` text COLLATE utf8mb4_unicode_ci,
  `dates_of_documents` text COLLATE utf8mb4_unicode_ci,
  `automatic_date_of_issue` date DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT '0',
  `customer` json NOT NULL,
  `parent_customer_id` int(10) unsigned DEFAULT '0',
  `parent_customer` json NOT NULL,
  `children_customer_id` int(10) unsigned DEFAULT '0',
  `children_customer` longtext COLLATE utf8mb4_unicode_ci,
  `quantity_period` int(10) unsigned DEFAULT '0',
  `apply_concurrency` int(10) unsigned NOT NULL DEFAULT '0',
  `enabled_concurrency` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'Se pasará a nota de ventas como activo',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_rate_sale` double(13,3) DEFAULT '0.000',
  `total_prepayment` double(12,2) DEFAULT '0.00',
  `total_charge` double(12,2) DEFAULT '0.00',
  `total_discount` double(12,2) DEFAULT '0.00',
  `total_exportation` double(12,2) DEFAULT '0.00',
  `total_free` double(12,2) DEFAULT '0.00',
  `total_taxed` double(12,2) DEFAULT '0.00',
  `total_unaffected` double(12,2) DEFAULT '0.00',
  `total_exonerated` double(12,2) DEFAULT '0.00',
  `total_igv` double(12,2) DEFAULT '0.00',
  `total_igv_free` double(12,2) DEFAULT '0.00',
  `total_base_other_taxes` double(12,2) DEFAULT '0.00',
  `total_other_taxes` double(12,2) DEFAULT '0.00',
  `total_taxes` double(12,2) DEFAULT '0.00',
  `total_value` double(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
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
  `grade` text COLLATE utf8mb4_unicode_ci COMMENT 'Grado designado - utilizado en matricula',
  `section` text COLLATE utf8mb4_unicode_ci COMMENT 'Seccion designado - utilizado en matricula',
  `orders_created` int(11) NOT NULL DEFAULT '0' COMMENT 'Cantidad de órdenes de cobro creadas para esta suscripción',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `user_rel_suscription_plans`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

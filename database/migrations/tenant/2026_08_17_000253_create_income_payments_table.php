<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `income_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `income_id` int(10) unsigned NOT NULL
 * - `date_of_payment` date NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `change` decimal(30,12) DEFAULT NULL
 * - `payment` decimal(30,12) NOT NULL
 * - `currency_type_id` varchar(255) DEFAULT NULL
 * - `original_amount` decimal(30,12) DEFAULT NULL
 * - `exchange_rate` decimal(18,8) DEFAULT NULL
 * - `exchange_rate_source` varchar(255) DEFAULT NULL
 * - `exchange_rate_date` date DEFAULT NULL
 * - `payment_method_snapshot` json DEFAULT NULL
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `income_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `income_id` int(10) unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change` decimal(30,12) DEFAULT NULL,
  `payment` decimal(30,12) NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_amount` decimal(30,12) DEFAULT NULL,
  `exchange_rate` decimal(18,8) DEFAULT NULL,
  `exchange_rate_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_rate_date` date DEFAULT NULL,
  `payment_method_snapshot` json DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`),
  KEY `income_payments_income_id_foreign` (`income_id`),
  KEY `income_payments_payment_method_type_id_foreign` (`payment_method_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `income_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

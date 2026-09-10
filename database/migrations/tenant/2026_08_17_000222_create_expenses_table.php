<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `expenses` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `expense_type_id` int(10) unsigned NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `supplier_id` int(10) unsigned NOT NULL
 * - `expense_reason_id` int(10) unsigned NOT NULL DEFAULT '1'
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `supplier` json NOT NULL
 * - `exchange_rate_sale` decimal(13,3) NOT NULL
 * - `total` decimal(12,2) NOT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expense_type_id` int(10) unsigned NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `expense_reason_id` int(10) unsigned NOT NULL DEFAULT '1',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `supplier` json NOT NULL,
  `exchange_rate_sale` decimal(13,3) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_filename_unique` (`filename`),
  KEY `expenses_user_id_foreign` (`user_id`),
  KEY `expenses_establishment_id_foreign` (`establishment_id`),
  KEY `expenses_supplier_id_foreign` (`supplier_id`),
  KEY `expenses_expense_type_id_foreign` (`expense_type_id`),
  KEY `expenses_currency_type_id_foreign` (`currency_type_id`),
  KEY `expenses_expense_reason_id_foreign` (`expense_reason_id`),
  KEY `expenses_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `expenses_state_type_id_foreign` (`state_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `expenses`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `income` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `income_type_id` int(10) unsigned NOT NULL
 * - `income_reason_id` int(10) unsigned NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `customer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` int(11) NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
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
CREATE TABLE `income` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `income_type_id` int(10) unsigned NOT NULL,
  `income_reason_id` int(10) unsigned NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `customer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `exchange_rate_sale` decimal(13,3) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `income_filename_unique` (`filename`),
  KEY `income_user_id_foreign` (`user_id`),
  KEY `income_establishment_id_foreign` (`establishment_id`),
  KEY `income_income_type_id_foreign` (`income_type_id`),
  KEY `income_income_reason_id_foreign` (`income_reason_id`),
  KEY `income_state_type_id_foreign` (`state_type_id`),
  KEY `income_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `income_currency_type_id_foreign` (`currency_type_id`),
  KEY `income_number_index` (`number`),
  KEY `income_date_of_issue_index` (`date_of_issue`),
  KEY `income_external_id_index` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `income`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

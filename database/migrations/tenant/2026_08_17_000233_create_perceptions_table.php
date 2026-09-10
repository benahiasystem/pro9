<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `perceptions` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` int(11) NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `customer_id` int(10) unsigned NOT NULL
 * - `customer` json NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `perception_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `observations` text COLLATE utf8mb4_unicode_ci
 * - `total_perception` decimal(10,2) NOT NULL
 * - `total` decimal(10,2) NOT NULL
 * - `optional` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `time_of_issue` time NOT NULL
 * - `has_pdf` tinyint(1) NOT NULL DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `perceptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubl_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perception_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `total_perception` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `optional` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_of_issue` time NOT NULL,
  `has_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `perceptions_user_id_foreign` (`user_id`),
  KEY `perceptions_establishment_id_foreign` (`establishment_id`),
  KEY `perceptions_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `perceptions_state_type_id_foreign` (`state_type_id`),
  KEY `perceptions_document_type_id_foreign` (`document_type_id`),
  KEY `perceptions_currency_type_id_foreign` (`currency_type_id`),
  KEY `perceptions_perception_type_id_foreign` (`perception_type_id`),
  KEY `perceptions_customer_id_foreign` (`customer_id`),
  KEY `perceptions_number_index` (`number`),
  KEY `perceptions_series_index` (`series`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `perceptions`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `order_forms` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `establishment` json NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `prefix` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `customer_id` int(10) unsigned NOT NULL
 * - `customer` json NOT NULL
 * - `observations` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `transport_mode_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `transfer_reason_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `transfer_reason_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date_of_shipping` date NOT NULL
 * - `transshipment_indicator` tinyint(1) NOT NULL
 * - `port_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total_weight` decimal(10,2) NOT NULL
 * - `packages_number` int(11) NOT NULL
 * - `container_number` int(11) DEFAULT NULL
 * - `origin` json NOT NULL
 * - `delivery` json NOT NULL
 * - `dispatcher_id` int(10) unsigned NOT NULL
 * - `driver_id` int(10) unsigned NOT NULL
 * - `license_plates` json DEFAULT NULL
 * - `legends` json DEFAULT NULL
 * - `optional` json DEFAULT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr` longtext COLLATE utf8mb4_unicode_ci
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `order_forms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `establishment` json NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` json NOT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `transport_mode_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_reason_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_reason_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_shipping` date NOT NULL,
  `transshipment_indicator` tinyint(1) NOT NULL,
  `port_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_weight` decimal(10,2) NOT NULL,
  `packages_number` int(11) NOT NULL,
  `container_number` int(11) DEFAULT NULL,
  `origin` json NOT NULL,
  `delivery` json NOT NULL,
  `dispatcher_id` int(10) unsigned NOT NULL,
  `driver_id` int(10) unsigned NOT NULL,
  `license_plates` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `optional` json DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_forms_dispatcher_id_foreign` (`dispatcher_id`),
  KEY `order_forms_driver_id_foreign` (`driver_id`),
  KEY `order_forms_user_id_foreign` (`user_id`),
  KEY `order_forms_establishment_id_foreign` (`establishment_id`),
  KEY `order_forms_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `order_forms_state_type_id_foreign` (`state_type_id`),
  KEY `order_forms_customer_id_foreign` (`customer_id`),
  KEY `order_forms_unit_type_id_foreign` (`unit_type_id`),
  KEY `order_forms_transport_mode_type_id_foreign` (`transport_mode_type_id`),
  KEY `order_forms_transfer_reason_type_id_foreign` (`transfer_reason_type_id`),
  KEY `order_forms_date_of_issue_index` (`date_of_issue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `order_forms`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

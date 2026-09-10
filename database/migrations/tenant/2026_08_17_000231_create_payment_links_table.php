<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `payment_links` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `user_id` int(10) unsigned NOT NULL
 * - `person_id` int(10) unsigned DEFAULT NULL
 * - `payment_link_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_id` int(11) DEFAULT NULL
 * - `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `total` decimal(12,2) NOT NULL
 * - `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
 * - `paid_at` datetime DEFAULT NULL
 * - `uploaded_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `query_transaction` tinyint(1) NOT NULL DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `person_id` int(10) unsigned DEFAULT NULL,
  `payment_link_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `uploaded_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `query_transaction` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_links_uuid_unique` (`uuid`),
  KEY `payment_index` (`payment_id`,`payment_type`),
  KEY `payment_links_user_id_foreign` (`user_id`),
  KEY `payment_links_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `payment_links_payment_link_type_id_foreign` (`payment_link_type_id`),
  KEY `payment_links_person_id_foreign` (`person_id`),
  KEY `payment_links_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_links`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

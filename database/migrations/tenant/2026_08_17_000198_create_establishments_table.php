<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `establishments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `department_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `province_id` char(4) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `district_id` char(6) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `telephone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `aditional_information` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `web_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `trade_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `customer_id` int(10) unsigned DEFAULT NULL
 * - `logo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `template_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default'
 * - `template_ticket_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `establishments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_id` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district_id` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aditional_information` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `web_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `logo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `template_ticket_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `establishments_country_id_foreign` (`country_id`),
  KEY `establishments_department_id_foreign` (`department_id`),
  KEY `establishments_province_id_foreign` (`province_id`),
  KEY `establishments_district_id_foreign` (`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `establishments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

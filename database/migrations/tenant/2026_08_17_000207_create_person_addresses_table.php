<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `person_addresses` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `person_id` int(10) unsigned NOT NULL
 * - `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `main` tinyint(1) NOT NULL DEFAULT '0'
 * - `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000'
 * - `has_consigned` tinyint(1) NOT NULL DEFAULT '0'
 * - `consigned_id` int(10) unsigned DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `person_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `main` tinyint(1) NOT NULL DEFAULT '0',
  `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000',
  `has_consigned` tinyint(1) NOT NULL DEFAULT '0',
  `consigned_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_addresses_person_id_foreign` (`person_id`),
  KEY `person_addresses_country_id_foreign` (`country_id`),
  KEY `person_addresses_department_id_foreign` (`department_id`),
  KEY `person_addresses_province_id_foreign` (`province_id`),
  KEY `person_addresses_district_id_foreign` (`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `person_addresses`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

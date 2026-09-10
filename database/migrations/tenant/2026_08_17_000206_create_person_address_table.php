<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `person_address` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `person_id` int(10) unsigned NOT NULL
 * - `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `person_address` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_address_person_id_foreign` (`person_id`),
  KEY `person_address_department_id_foreign` (`department_id`),
  KEY `person_address_province_id_foreign` (`province_id`),
  KEY `person_address_district_id_foreign` (`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `person_address`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

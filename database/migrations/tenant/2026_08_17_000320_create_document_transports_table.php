<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `document_transports` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `seat_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `passenger_manifest` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number_identity_document` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `passenger_fullname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `origin_district_id` json NOT NULL
 * - `origin_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `destinatation_district_id` json NOT NULL
 * - `destinatation_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `start_date` date DEFAULT NULL
 * - `start_time` time DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_transports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `seat_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passenger_manifest` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_identity_document` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passenger_fullname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin_district_id` json NOT NULL,
  `origin_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatation_district_id` json NOT NULL,
  `destinatation_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_transports_document_id_foreign` (`document_id`),
  KEY `document_transports_identity_document_type_id_foreign` (`identity_document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `document_transports`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `document_hotels` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `sex` enum('M','F') COLLATE utf8mb4_unicode_ci NOT NULL
 * - `age` int(11) NOT NULL
 * - `civil_status` enum('S','C','V','D') COLLATE utf8mb4_unicode_ci NOT NULL
 * - `nacionality` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `origin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `room_number` int(11) NOT NULL
 * - `date_entry` date NOT NULL
 * - `time_entry` time NOT NULL
 * - `date_exit` date NOT NULL
 * - `time_exit` time NOT NULL
 * - `ocupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `room_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `guests` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_hotels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sex` enum('M','F') COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` int(11) NOT NULL,
  `civil_status` enum('S','C','V','D') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nacionality` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_number` int(11) NOT NULL,
  `date_entry` date NOT NULL,
  `time_entry` time NOT NULL,
  `date_exit` date NOT NULL,
  `time_exit` time NOT NULL,
  `ocupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guests` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_hotels_document_id_foreign` (`document_id`),
  KEY `document_hotels_identity_document_type_id_foreign` (`identity_document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `document_hotels`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

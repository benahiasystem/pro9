<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `workers` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `birth_date` date NOT NULL
 * - `admission_date` date NOT NULL
 * - `occupation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `workers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_date` date NOT NULL,
  `admission_date` date NOT NULL,
  `occupation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `workers_identity_document_type_id_foreign` (`identity_document_type_id`),
  KEY `workers_number_index` (`number`),
  KEY `workers_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `workers`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

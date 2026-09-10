<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `guides` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `user_id` int(10) unsigned NOT NULL
 * - `warehouse_id` int(10) unsigned NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `document_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` int(11) NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `time_of_issue` time NOT NULL
 * - `inventory_transaction_id` char(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `guideable_id` int(10) unsigned DEFAULT NULL
 * - `guideable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `observations` longtext COLLATE utf8mb4_unicode_ci
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `guides` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `inventory_transaction_id` char(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guideable_id` int(10) unsigned DEFAULT NULL,
  `guideable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` longtext COLLATE utf8mb4_unicode_ci,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `guides_series_number_unique` (`fiscal_environment`,`document_type_id`,`series`,`number`),
  KEY `guides_user_id_foreign` (`user_id`),
  KEY `guides_warehouse_id_foreign` (`warehouse_id`),
  KEY `guides_document_type_id_foreign` (`document_type_id`),
  KEY `guides_inventory_transaction_id_foreign` (`inventory_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `guides`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

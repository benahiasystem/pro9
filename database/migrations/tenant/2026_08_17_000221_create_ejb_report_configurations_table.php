<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `ejb_report_configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `bank_account_pen_id` int(10) unsigned DEFAULT NULL
 * - `bank_account_usd_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `ejb_report_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_pen_id` int(10) unsigned DEFAULT NULL,
  `bank_account_usd_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ejb_report_configurations_document_type_id_foreign` (`document_type_id`),
  KEY `ejb_report_configurations_bank_account_pen_id_foreign` (`bank_account_pen_id`),
  KEY `ejb_report_configurations_bank_account_usd_id_foreign` (`bank_account_usd_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `ejb_report_configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

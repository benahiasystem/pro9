<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `retention_documents` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `retention_id` int(10) unsigned NOT NULL
 * - `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date_of_issue` date NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total_document` decimal(10,2) NOT NULL
 * - `payments` json NOT NULL
 * - `exchange_rate` json NOT NULL
 * - `date_of_retention` date NOT NULL
 * - `total_retention` decimal(10,2) NOT NULL
 * - `total_to_pay` decimal(10,2) NOT NULL
 * - `total_payment` decimal(10,2) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `retention_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `retention_id` int(10) unsigned NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_document` decimal(10,2) NOT NULL,
  `payments` json NOT NULL,
  `exchange_rate` json NOT NULL,
  `date_of_retention` date NOT NULL,
  `total_retention` decimal(10,2) NOT NULL,
  `total_to_pay` decimal(10,2) NOT NULL,
  `total_payment` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `retention_documents_retention_id_foreign` (`retention_id`),
  KEY `retention_documents_document_type_id_foreign` (`document_type_id`),
  KEY `retention_documents_currency_type_id_foreign` (`currency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `retention_documents`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

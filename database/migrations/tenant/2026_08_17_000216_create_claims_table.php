<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `claims` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `public_code` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `tracking_number` smallint(5) unsigned NOT NULL DEFAULT '0'
 * - `parent_code` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `identity_document_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `identity_document_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `address` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `asset_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `asset_description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `asset_date` date NOT NULL
 * - `has_receipt` tinyint(1) NOT NULL DEFAULT '0'
 * - `receipt_series` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `receipt_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `receipt_amount` decimal(10,2) DEFAULT NULL
 * - `receipt_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `claim_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `detail` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `expected_result` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `channel` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `attachments` json DEFAULT NULL
 * - `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `terms_accepted` tinyint(1) NOT NULL DEFAULT '0'
 * - `status_claim_id` int(10) unsigned DEFAULT NULL
 * - `assigned_user_id` int(10) unsigned DEFAULT NULL
 * - `resolution` text COLLATE utf8mb4_unicode_ci
 * - `response_attachments` json DEFAULT NULL
 * - `is_closed` tinyint(1) NOT NULL DEFAULT '0'
 * - `closed_at` timestamp NULL DEFAULT NULL
 * - `due_date` date DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `claims` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_code` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_number` smallint(5) unsigned NOT NULL DEFAULT '0',
  `parent_code` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_document_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identity_document_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asset_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_date` date NOT NULL,
  `has_receipt` tinyint(1) NOT NULL DEFAULT '0',
  `receipt_series` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_amount` decimal(10,2) DEFAULT NULL,
  `receipt_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claim_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_result` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachments` json DEFAULT NULL,
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_accepted` tinyint(1) NOT NULL DEFAULT '0',
  `status_claim_id` int(10) unsigned DEFAULT NULL,
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `response_attachments` json DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  `closed_at` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `claims_code_unique` (`code`),
  UNIQUE KEY `claims_public_code_unique` (`public_code`),
  KEY `claims_status_claim_id_foreign` (`status_claim_id`),
  KEY `claims_district_id_foreign` (`district_id`),
  KEY `claims_parent_code_index` (`parent_code`),
  KEY `claims_assigned_user_id_foreign` (`assigned_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `claims`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

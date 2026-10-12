<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_received_retentions: esquema inicial para instalaciones nuevas.
 * - `fiscal_context` json DEFAULT NULL (dated profiles, decision and administrative evidence)
 * - `application_status` varchar(24) NOT NULL DEFAULT 'applied'; pending/invalid/reversed rows settle zero
 * - operation_key/reversal_key nullable UUIDs, input_hash SHA-256 and input_snapshot private JSON
 * - taxable_base decimal(12,2), diagnostic, reversed_at/reversed_by/reversal_reason audit
 * - active_voucher_number generated varchar(100) nullable; uniqueness applies only to active vouchers
 * - active_iva_document_id generated nullable document ID; UNIQUE limits one pending/applied IVA
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `tax_kind` varchar(4) NOT NULL
 * - `voucher_number` varchar(100) NOT NULL
 * - `voucher_date` date NOT NULL
 * - `agent_id` int(10) unsigned NOT NULL
 * - `agent` json NOT NULL
 * - `agent_number` varchar(32) NOT NULL
 * - `concept_id` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `base` decimal(12,2) NOT NULL DEFAULT 0
 * - `percentage` decimal(12,2) NOT NULL DEFAULT 0
 * - `subtrahend` decimal(12,2) NOT NULL DEFAULT 0
 * - `amount` decimal(12,2) NOT NULL DEFAULT 0
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `exchange_rate` decimal(18,8) NOT NULL
 * - `applied_amount` decimal(12,2) NOT NULL DEFAULT 0
 * - `attachment` varchar(255) DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_received_retentions` (
  `fiscal_context` json DEFAULT NULL,
  `application_status` varchar(24) NOT NULL DEFAULT 'applied',
  `operation_key` char(36) DEFAULT NULL,
  `input_hash` char(64) DEFAULT NULL,
  `input_snapshot` json DEFAULT NULL,
  `taxable_base` decimal(12,2) NOT NULL DEFAULT 0,
  `diagnostic` varchar(1000) DEFAULT NULL,
  `reversal_key` char(36) DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` int(10) unsigned DEFAULT NULL,
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `tax_kind` varchar(4) NOT NULL,
  `voucher_number` varchar(100) NOT NULL,
  `voucher_date` date NOT NULL,
  `agent_id` int(10) unsigned NOT NULL,
  `agent` json NOT NULL,
  `agent_number` varchar(32) NOT NULL,
  `concept_id` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base` decimal(12,2) NOT NULL DEFAULT 0,
  `percentage` decimal(12,2) NOT NULL DEFAULT 0,
  `subtrahend` decimal(12,2) NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exchange_rate` decimal(18,8) NOT NULL,
  `applied_amount` decimal(12,2) NOT NULL DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `active_iva_document_id` int(10) unsigned GENERATED ALWAYS AS (CASE WHEN tax_kind = 'IVA' AND application_status IN ('pending_validation','applied') THEN document_id ELSE NULL END) STORED,
  `active_voucher_number` varchar(100) GENERATED ALWAYS AS (CASE WHEN application_status IN ('pending_validation','applied') THEN voucher_number ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `received_iva_active_unique` (`active_iva_document_id`),
  UNIQUE KEY `received_retention_operation_unique` (`operation_key`),
  UNIQUE KEY `received_retention_reversal_unique` (`reversal_key`),
  UNIQUE KEY `received_retention_voucher_unique` (`document_id`,`tax_kind`,`agent_number`,`active_voucher_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_received_retentions`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

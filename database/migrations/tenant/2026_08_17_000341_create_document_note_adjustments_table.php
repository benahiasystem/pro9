<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO NOTAS HKA: ESQUEMA INICIAL ########
/** New installations only. Amounts DECIMAL(12,2), rates DECIMAL(18,8); FKs in 000999. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_note_adjustments` (
  `kind` enum('IVA','ISLR','IGTF','fund') NOT NULL,
  `retention_id` int unsigned DEFAULT NULL,
  `tax_id` int unsigned DEFAULT NULL,
  `guarantee_fund_id` int unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnostic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request` json NOT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  KEY `document_note_adjustments_retention_index` (`retention_id`),
  KEY `document_note_adjustments_tax_index` (`tax_id`),
  KEY `document_note_adjustments_fund_index` (`guarantee_fund_id`),
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `affected_document_id` int unsigned NOT NULL,
  `operation_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` int unsigned NOT NULL,
  `context` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_note_adjustments_operation_unique` (`operation_key`),
  KEY `document_note_adjustments_document_index` (`document_id`),
  KEY `document_note_adjustments_affected_index` (`affected_document_id`),
  KEY `document_note_adjustments_actor_index` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_note_adjustments`'); }
};
// ######## FIN NOTAS HKA ########

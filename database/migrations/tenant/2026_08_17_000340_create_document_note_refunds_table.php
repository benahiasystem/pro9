<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO NOTAS HKA: ESQUEMA INICIAL ########
/** New installations only. Amounts DECIMAL(12,2), rates DECIMAL(18,8); FKs in 000999. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_note_refunds` (
  `document_payment_id` int unsigned NOT NULL,
  `adjustment_id` int unsigned DEFAULT NULL,
  `component` enum('principal','IGTF') NOT NULL DEFAULT 'principal',
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_amount` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `exchange_rate` decimal(18,8) NOT NULL,
  `exchange_rate_source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` int unsigned DEFAULT NULL,
  `reversal_key` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  UNIQUE KEY `document_note_refunds_reversal_unique` (`reversal_key`),
  KEY `document_note_refunds_payment_index` (`document_payment_id`),
  KEY `document_note_refunds_adjustment_index` (`adjustment_id`),
  KEY `document_note_refunds_reversed_by_index` (`reversed_by`),
  KEY `document_note_refunds_method_index` (`payment_method_type_id`),
  KEY `document_note_refunds_currency_index` (`currency_type_id`),
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
  UNIQUE KEY `document_note_refunds_operation_unique` (`operation_key`),
  KEY `document_note_refunds_document_index` (`document_id`),
  KEY `document_note_refunds_affected_index` (`affected_document_id`),
  KEY `document_note_refunds_actor_index` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_note_refunds`'); }
};
// ######## FIN NOTAS HKA ########

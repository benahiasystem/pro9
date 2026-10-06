<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_received_retentions: esquema inicial para instalaciones nuevas.
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
 * - `exchange_rate` decimal(13,3) NOT NULL
 * - `applied_amount` decimal(12,2) NOT NULL DEFAULT 0
 * - `attachment` varchar(255) DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_received_retentions` (
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
  `exchange_rate` decimal(13,3) NOT NULL,
  `applied_amount` decimal(12,2) NOT NULL DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `received_retention_voucher_unique` (`document_id`,`tax_kind`,`agent_number`,`voucher_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_received_retentions`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

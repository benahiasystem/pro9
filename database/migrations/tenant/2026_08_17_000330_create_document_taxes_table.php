<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_taxes: esquema inicial para instalaciones nuevas.
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `document_payment_id` int(10) unsigned DEFAULT NULL
 * - `tax_kind` varchar(16) NOT NULL
 * - `code` varchar(32) NOT NULL
 * - `percentage` decimal(12,2) NOT NULL DEFAULT 0
 * - `base` decimal(12,2) NOT NULL DEFAULT 0
 * - `amount` decimal(12,2) NOT NULL DEFAULT 0
 * - `base_ves` decimal(12,2) NOT NULL DEFAULT 0
 * - `amount_ves` decimal(12,2) NOT NULL DEFAULT 0
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_taxes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `document_payment_id` int(10) unsigned DEFAULT NULL,
  `tax_kind` varchar(16) NOT NULL,
  `code` varchar(32) NOT NULL,
  `percentage` decimal(12,2) NOT NULL DEFAULT 0,
  `base` decimal(12,2) NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0,
  `base_ves` decimal(12,2) NOT NULL DEFAULT 0,
  `amount_ves` decimal(12,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_taxes_payment_unique` (`document_payment_id`),
  KEY `document_taxes_document_index` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_taxes`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

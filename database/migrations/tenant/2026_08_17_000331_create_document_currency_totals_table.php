<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_currency_totals: esquema inicial para instalaciones nuevas.
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `taxed` decimal(12,2) NOT NULL DEFAULT 0
 * - `exempt` decimal(12,2) NOT NULL DEFAULT 0
 * - `discount` decimal(12,2) NOT NULL DEFAULT 0
 * - `charge` decimal(12,2) NOT NULL DEFAULT 0
 * - `iva` decimal(12,2) NOT NULL DEFAULT 0
 * - `other_taxes` decimal(12,2) NOT NULL DEFAULT 0
 * - `total` decimal(12,2) NOT NULL DEFAULT 0
 * - `tax_breakdown` json NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_currency_totals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taxed` decimal(12,2) NOT NULL DEFAULT 0,
  `exempt` decimal(12,2) NOT NULL DEFAULT 0,
  `discount` decimal(12,2) NOT NULL DEFAULT 0,
  `charge` decimal(12,2) NOT NULL DEFAULT 0,
  `iva` decimal(12,2) NOT NULL DEFAULT 0,
  `other_taxes` decimal(12,2) NOT NULL DEFAULT 0,
  `total` decimal(12,2) NOT NULL DEFAULT 0,
  `tax_breakdown` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_currency_totals_document_unique` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_currency_totals`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

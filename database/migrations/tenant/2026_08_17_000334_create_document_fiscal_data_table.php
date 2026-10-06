<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_fiscal_data: esquema inicial para instalaciones nuevas.
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `provider_type_id` tinyint unsigned DEFAULT NULL
 * - `transaction_type_id` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01'
 * - `special_tax_regime_id` tinyint unsigned DEFAULT NULL
 * - `catalog_snapshot` json NOT NULL
 * - `third_party` json DEFAULT NULL
 * - `conditional_data` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_fiscal_data` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `provider_type_id` tinyint unsigned DEFAULT NULL,
  `transaction_type_id` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01',
  `special_tax_regime_id` tinyint unsigned DEFAULT NULL,
  `catalog_snapshot` json NOT NULL,
  `third_party` json DEFAULT NULL,
  `conditional_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_fiscal_data_document_unique` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_fiscal_data`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

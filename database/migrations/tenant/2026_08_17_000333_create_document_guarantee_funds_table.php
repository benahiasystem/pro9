<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_guarantee_funds: esquema inicial para instalaciones nuevas.
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `amount` decimal(12,2) NOT NULL DEFAULT 0
 * - `base` decimal(12,2) NOT NULL DEFAULT 0
 * - `percentage` decimal(12,2) NOT NULL DEFAULT 0
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_guarantee_funds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0,
  `base` decimal(12,2) NOT NULL DEFAULT 0,
  `percentage` decimal(12,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_guarantee_funds_document_unique` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_guarantee_funds`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

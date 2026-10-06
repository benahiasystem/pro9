<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/**
 * document_emissions: esquema inicial para instalaciones nuevas.
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `provider` varchar(32) NOT NULL DEFAULT 'HKA'
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `contract_version` varchar(100) DEFAULT NULL
 * - `operation_key` char(36) NOT NULL
 * - `status` enum('not_requested','prepared','pending','confirmed','rejected','uncertain','cancelled') NOT NULL DEFAULT 'not_requested'
 * - `payload` json DEFAULT NULL
 * - `response` json DEFAULT NULL
 * - `control_number` varchar(32) DEFAULT NULL
 * - `authorization` varchar(255) DEFAULT NULL
 * - `assigned_at` datetime DEFAULT NULL
 * - `control_assigned_at` datetime DEFAULT NULL
 * - `file_path` varchar(255) DEFAULT NULL
 * - `consulta_url` varchar(255) DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_emissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `provider` varchar(32) NOT NULL DEFAULT 'HKA',
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_version` varchar(100) DEFAULT NULL,
  `operation_key` char(36) NOT NULL,
  `status` enum('not_requested','prepared','pending','confirmed','rejected','uncertain','cancelled') NOT NULL DEFAULT 'not_requested',
  `payload` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `control_number` varchar(32) DEFAULT NULL,
  `authorization` varchar(255) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `control_assigned_at` datetime DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `consulta_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_emissions_document_unique` (`document_id`),
  UNIQUE KEY `document_emissions_operation_unique` (`operation_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_emissions`'); }
};
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

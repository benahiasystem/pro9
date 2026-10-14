<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ORDENES HKA DEMO ########
/** dispatch_emissions: one durable operation per order, private snapshots and provider results. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_emissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dispatch_id` int unsigned NOT NULL,
  `provider` varchar(32) NOT NULL DEFAULT 'HKA',
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operation_key` char(36) NOT NULL,
  `contract_version` varchar(100) DEFAULT NULL,
  `status` enum('not_requested','prepared','pending','confirmed','rejected','uncertain','cancelled') NOT NULL DEFAULT 'not_requested',
  `snapshot` json NOT NULL,
  `payload` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `control_number` varchar(32) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `consulta_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispatch_emissions_dispatch_unique` (`dispatch_id`),
  UNIQUE KEY `dispatch_emissions_operation_unique` (`operation_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `dispatch_emissions`'); }
};
// ######## FIN ORDENES HKA DEMO ########

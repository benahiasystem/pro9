<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Initial tenant schema; no conversion of existing tenants.
 * `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 * `document_id` int(10) unsigned NOT NULL,
 * `retention_id` int(10) unsigned NOT NULL,
 * `operation_key` char(36) NOT NULL,
 * `action` varchar(8) NOT NULL,
 * `status` varchar(24) NOT NULL DEFAULT 'waiting',
 * `payload` json NOT NULL,
 * `context` json NOT NULL,
 * `result` json DEFAULT NULL,
 * `attempt_id` char(36) DEFAULT NULL,
 * `attempts` int unsigned NOT NULL DEFAULT 0,
 * `failures` int unsigned NOT NULL DEFAULT 0,
 * `started_at` timestamp NULL DEFAULT NULL,
 * `next_attempt_at` timestamp NULL DEFAULT NULL,
 * `diagnostic` varchar(1000) DEFAULT NULL,
 * `actor_id` int(10) unsigned DEFAULT NULL,
 * `created_at` timestamp NULL DEFAULT NULL,
 * `updated_at` timestamp NULL DEFAULT NULL,
 * PRIMARY KEY (`id`),
 * UNIQUE KEY `islr_operation_key_unique` (`operation_key`),
 * UNIQUE KEY `islr_retention_action_unique` (`retention_id`,`action`),
 * KEY `islr_operations_due_index` (`status`,`next_attempt_at`)
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_islr_retention_operations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `retention_id` int(10) unsigned NOT NULL,
  `operation_key` char(36) NOT NULL,
  `action` varchar(8) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'waiting',
  `payload` json NOT NULL,
  `context` json NOT NULL,
  `result` json DEFAULT NULL,
  `attempt_id` char(36) DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `failures` int unsigned NOT NULL DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `next_attempt_at` timestamp NULL DEFAULT NULL,
  `diagnostic` varchar(1000) DEFAULT NULL,
  `actor_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `islr_operation_key_unique` (`operation_key`),
  UNIQUE KEY `islr_retention_action_unique` (`retention_id`,`action`),
  KEY `islr_operations_due_index` (`status`,`next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_islr_retention_operations`'); }
};

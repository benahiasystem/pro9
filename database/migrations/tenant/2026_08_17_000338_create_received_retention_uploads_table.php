<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Initial tenant schema; no conversion of existing tenants.
 * `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 * `token` char(36) NOT NULL,
 * `user_id` int(10) unsigned NOT NULL,
 * `document_id` int(10) unsigned DEFAULT NULL,
 * `path` varchar(255) NOT NULL,
 * `expires_at` timestamp NOT NULL,
 * `created_at` timestamp NULL DEFAULT NULL,
 * `updated_at` timestamp NULL DEFAULT NULL,
 * PRIMARY KEY (`id`),
 * UNIQUE KEY `retention_upload_token_unique` (`token`),
 * KEY `retention_upload_expiry_index` (`expires_at`)
 */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `received_retention_uploads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` char(36) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retention_upload_token_unique` (`token`),
  KEY `retention_upload_expiry_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `received_retention_uploads`'); }
};

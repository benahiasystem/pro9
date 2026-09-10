<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `system_activity_logs` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned DEFAULT NULL
 * - `system_activity_log_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date` date NOT NULL
 * - `time` time NOT NULL
 * - `origin_id` int(11) DEFAULT NULL
 * - `origin_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `device_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `platform_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `platform_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `browser_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `browser_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `request_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `location` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `system_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `system_activity_log_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `origin_id` int(11) DEFAULT NULL,
  `origin_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_index` (`origin_id`,`origin_type`),
  KEY `system_activity_logs_user_id_foreign` (`user_id`),
  KEY `system_activity_logs_auth_transaction_type_index` (`system_activity_log_type_id`),
  KEY `system_activity_logs_date_index` (`date`),
  KEY `system_activity_logs_time_index` (`time`),
  KEY `system_activity_logs_device_type_index` (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `system_activity_logs`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

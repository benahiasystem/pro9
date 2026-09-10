<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `webhook_deliveries` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `webhook_subscription_id` bigint(20) unsigned NOT NULL
 * - `event` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `event_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payload` json NOT NULL
 * - `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
 * - `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0'
 * - `response_code` smallint(6) DEFAULT NULL
 * - `response_body` text COLLATE utf8mb4_unicode_ci
 * - `error_message` text COLLATE utf8mb4_unicode_ci
 * - `sent_at` timestamp NULL DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `webhook_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_subscription_id` bigint(20) unsigned NOT NULL,
  `event` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `response_code` smallint(6) DEFAULT NULL,
  `response_body` text COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhook_deliveries_webhook_subscription_id_status_index` (`webhook_subscription_id`,`status`),
  KEY `webhook_deliveries_created_at_index` (`created_at`),
  KEY `webhook_deliveries_event_index` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `webhook_deliveries`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

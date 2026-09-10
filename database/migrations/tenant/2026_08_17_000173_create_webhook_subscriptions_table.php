<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `webhook_subscriptions` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `secret` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `events` json NOT NULL
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 * - `consecutive_failures` smallint(5) unsigned NOT NULL DEFAULT '0'
 * - `disabled_at` timestamp NULL DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `webhook_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `events` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `consecutive_failures` smallint(5) unsigned NOT NULL DEFAULT '0',
  `disabled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhook_subscriptions_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `webhook_subscriptions`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

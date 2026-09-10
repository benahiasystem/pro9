<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `bot_sessions` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `state` enum('idle','active','awaiting_confirm','paused','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'idle'
 * - `context_json` json DEFAULT NULL
 * - `pending_document_json` json DEFAULT NULL
 * - `last_activity_at` timestamp NULL DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `bot_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` enum('idle','active','awaiting_confirm','paused','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'idle',
  `context_json` json DEFAULT NULL,
  `pending_document_json` json DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bot_sessions_customer_phone_index` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `bot_sessions`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

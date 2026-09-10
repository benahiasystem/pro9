<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `bot_messages` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `session_id` bigint(20) unsigned DEFAULT NULL
 * - `direction` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL
 * - `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `body` text COLLATE utf8mb4_unicode_ci
 * - `message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `from_me` tinyint(1) NOT NULL DEFAULT '0'
 * - `raw_payload` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `bot_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned DEFAULT NULL,
  `direction` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_me` tinyint(1) NOT NULL DEFAULT '0',
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bot_messages_session_id_index` (`session_id`),
  KEY `bot_messages_phone_index` (`phone`),
  KEY `bot_messages_message_id_index` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `bot_messages`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

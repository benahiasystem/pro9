<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `whatsapp_message_logs` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `whatsapp_message_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `whatsapp_message_logs`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

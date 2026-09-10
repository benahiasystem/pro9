<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cash_document_credits` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `cash_id` int(10) unsigned NOT NULL
 * - `cash_id_processed` int(10) unsigned DEFAULT NULL
 * - `document_id` int(10) unsigned DEFAULT NULL
 * - `sale_note_id` int(10) unsigned DEFAULT NULL
 * - `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_document_credits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_id` int(10) unsigned NOT NULL,
  `cash_id_processed` int(10) unsigned DEFAULT NULL,
  `document_id` int(10) unsigned DEFAULT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_document_credits_document_id_foreign` (`document_id`),
  KEY `cash_document_credits_sale_note_id_foreign` (`sale_note_id`),
  KEY `cash_document_credits_cash_id_foreign` (`cash_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cash_document_credits`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

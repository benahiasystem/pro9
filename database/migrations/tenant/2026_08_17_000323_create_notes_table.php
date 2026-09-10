<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `notes` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `note_type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL
 * - `note_credit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `note_debit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `note_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `affected_document_id` int(10) unsigned DEFAULT NULL
 * - `data_affected_document` json DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `note_type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note_credit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note_debit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `affected_document_id` int(10) unsigned DEFAULT NULL,
  `data_affected_document` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notes_document_id_foreign` (`document_id`),
  KEY `notes_note_credit_type_id_foreign` (`note_credit_type_id`),
  KEY `notes_note_debit_type_id_foreign` (`note_debit_type_id`),
  KEY `notes_affected_document_id_foreign` (`affected_document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `notes`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

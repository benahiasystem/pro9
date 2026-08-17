<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `notes`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `note_type`: enum('credit','debit'); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `note_credit_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `note_debit_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `note_description`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `affected_document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `data_affected_document`: json; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

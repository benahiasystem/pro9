<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `voided_documents`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `voided_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `voided_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voided_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `voided_documents_voided_id_foreign` (`voided_id`),
  KEY `voided_documents_document_id_foreign` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `voided_documents`');
    }
};

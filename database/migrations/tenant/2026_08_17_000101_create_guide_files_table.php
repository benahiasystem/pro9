<?php

/**
         * Run the migrations.
         *
         * @return void
         */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `guide_files`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `filename`: text; NULL; COLLATE utf8mb4_unicode_ci — Nombre de archivo
 * - `purchase_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con purchases
 * - `document_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con documents
 * - `order_note_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con order_notes
 * - `quotation_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con quotations
 * - `sale_note_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con sale_notes
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `guide_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` text COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de archivo',
  `purchase_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con purchases',
  `document_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con documents',
  `order_note_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con order_notes',
  `quotation_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con quotations',
  `sale_note_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con sale_notes',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `guide_files`');
    }
};

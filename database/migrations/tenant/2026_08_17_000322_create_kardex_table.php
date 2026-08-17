<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `kardex`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `type`: enum('sale','purchase'); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `purchase_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `purchase_settlement_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `kardex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_of_issue` date NOT NULL,
  `type` enum('sale','purchase') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned DEFAULT NULL,
  `purchase_id` int(10) unsigned DEFAULT NULL,
  `purchase_settlement_id` int(10) unsigned DEFAULT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kardex_purchase_id_foreign` (`purchase_id`),
  KEY `kardex_document_id_foreign` (`document_id`),
  KEY `kardex_item_id_foreign` (`item_id`),
  KEY `kardex_sale_note_id_foreign` (`sale_note_id`),
  KEY `kardex_purchase_settlement_id_foreign` (`purchase_settlement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `kardex`');
    }
};

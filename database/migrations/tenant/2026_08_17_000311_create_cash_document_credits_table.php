<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cash_document_credits`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `cash_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `cash_id_processed`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `status`: varchar(15); NOT NULL; DEFAULT PENDING; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
// ######### FIN CAMBIO NELSON #########

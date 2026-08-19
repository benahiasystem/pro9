<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cash_document_payments`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `cash_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `document_payment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sale_note_payment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `cash_document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `cash_document_credit_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `cash_document_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_id` int(10) unsigned DEFAULT NULL,
  `document_payment_id` int(10) unsigned DEFAULT NULL,
  `sale_note_payment_id` int(10) unsigned DEFAULT NULL,
  `cash_document_id` int(10) unsigned DEFAULT NULL,
  `cash_document_credit_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_document_payments_cash_id_foreign` (`cash_id`),
  KEY `cash_document_payments_document_payment_id_foreign` (`document_payment_id`),
  KEY `cash_document_payments_sale_note_payment_id_foreign` (`sale_note_payment_id`),
  KEY `cash_document_payments_cash_document_id_foreign` (`cash_document_id`),
  KEY `cash_document_payments_cash_document_credit_id_foreign` (`cash_document_credit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cash_document_payments`');
    }
};
// ######### FIN CAMBIO NELSON #########

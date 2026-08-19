<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cash_documents`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `cash_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `technical_service_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `expense_payment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `purchase_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `bank_loan_payment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `quotation_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned DEFAULT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `technical_service_id` int(10) unsigned DEFAULT NULL,
  `expense_payment_id` int(10) unsigned DEFAULT NULL,
  `purchase_id` int(10) unsigned DEFAULT NULL,
  `bank_loan_payment_id` int(10) unsigned DEFAULT NULL,
  `quotation_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_documents_cash_id_foreign` (`cash_id`),
  KEY `cash_documents_document_id_foreign` (`document_id`),
  KEY `cash_documents_sale_note_id_foreign` (`sale_note_id`),
  KEY `cash_documents_expense_payment_id_foreign` (`expense_payment_id`),
  KEY `cash_documents_technical_service_id_foreign` (`technical_service_id`),
  KEY `cash_documents_purchase_id_foreign` (`purchase_id`),
  KEY `cash_documents_quotation_id_foreign` (`quotation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cash_documents`');
    }
};
// ######### FIN CAMBIO NELSON #########

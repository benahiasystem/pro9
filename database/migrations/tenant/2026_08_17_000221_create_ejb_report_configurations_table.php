<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `ejb_report_configurations`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `bank_account_pen_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `bank_account_usd_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `ejb_report_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_pen_id` int(10) unsigned DEFAULT NULL,
  `bank_account_usd_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ejb_report_configurations_document_type_id_foreign` (`document_type_id`),
  KEY `ejb_report_configurations_bank_account_pen_id_foreign` (`bank_account_pen_id`),
  KEY `ejb_report_configurations_bank_account_usd_id_foreign` (`bank_account_usd_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `ejb_report_configurations`');
    }
};
// ######### FIN CAMBIO NELSON #########

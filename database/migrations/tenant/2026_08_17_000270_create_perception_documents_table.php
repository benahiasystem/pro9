<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `perception_documents`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `perception_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_perception`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_document`: decimal(10,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_perception`: decimal(10,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `payments`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `series`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `exchange_rate`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_to_pay`: decimal(10,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_payment`: decimal(10,2); NOT NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `perception_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `perception_id` int(10) unsigned NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_issue` date NOT NULL,
  `date_of_perception` date NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_document` decimal(10,2) NOT NULL,
  `total_perception` decimal(10,2) NOT NULL,
  `payments` json NOT NULL,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exchange_rate` json NOT NULL,
  `total_to_pay` decimal(10,2) NOT NULL,
  `total_payment` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `perception_details_perception_id_foreign` (`perception_id`),
  KEY `perception_documents_document_type_id_foreign` (`document_type_id`),
  KEY `perception_documents_currency_type_id_foreign` (`currency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `perception_documents`');
    }
};

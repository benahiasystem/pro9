<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `tips`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date`: date; NOT NULL — Fecha de registro
 * - `origin_date_of_issue`: date; NOT NULL — Fecha del documento origen de la propina
 * - `origin_id`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `origin_type`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `worker_full_name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `tips` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL COMMENT 'Fecha de registro',
  `origin_date_of_issue` date NOT NULL COMMENT 'Fecha del documento origen de la propina',
  `origin_id` int(11) NOT NULL,
  `origin_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `worker_full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `origin_index` (`origin_id`,`origin_type`),
  KEY `tips_soap_type_id_foreign` (`soap_type_id`),
  KEY `tips_date_index` (`date`),
  KEY `tips_origin_date_of_issue_index` (`origin_date_of_issue`),
  KEY `tips_worker_full_name_index` (`worker_full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `tips`');
    }
};

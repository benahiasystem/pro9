<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `series`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `contingency`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `dedicated`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `series_device_group_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `in_use`: tinyint(1); NOT NULL; DEFAULT 0 — True al emitir el primer comprobante en la serie
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
CREATE TABLE `series` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `establishment_id` int(10) unsigned NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contingency` tinyint(1) NOT NULL DEFAULT '0',
  `dedicated` tinyint(1) NOT NULL DEFAULT '0',
  `series_device_group_id` int(10) unsigned DEFAULT NULL,
  `in_use` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True al emitir el primer comprobante en la serie',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `series_establishment_id_foreign` (`establishment_id`),
  KEY `series_document_type_id_foreign` (`document_type_id`),
  KEY `series_series_device_group_id_foreign` (`series_device_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `series`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `user_default_document_types`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `series_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `user_default_document_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_default_document_types_user_id_foreign` (`user_id`),
  KEY `user_default_document_types_document_type_id_foreign` (`document_type_id`),
  KEY `user_default_document_types_series_id_foreign` (`series_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `user_default_document_types`');
    }
};
// ######### FIN CAMBIO NELSON #########

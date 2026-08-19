<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `app_configurations`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `show_image_item`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `print_format_pdf`: varchar(255); NOT NULL; DEFAULT ticket; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `card_color`: varchar(255); NOT NULL; DEFAULT multicolored; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `theme_color`: varchar(255); NOT NULL; DEFAULT blue; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `header_waves`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `direct_send_documents_whatsapp`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `primary_color`: varchar(255); NULL; DEFAULT #020F3C; COLLATE utf8mb4_unicode_ci — Color principal para la nueva app movil - hexadecimal
 * - `direct_print`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `app_mode`: varchar(255); NOT NULL; DEFAULT default; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `app_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `show_image_item` tinyint(1) NOT NULL DEFAULT '1',
  `print_format_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ticket',
  `card_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multicolored',
  `theme_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `header_waves` tinyint(1) NOT NULL DEFAULT '0',
  `direct_send_documents_whatsapp` tinyint(1) NOT NULL DEFAULT '0',
  `primary_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#020F3C' COMMENT 'Color principal para la nueva app movil - hexadecimal',
  `direct_print` tinyint(1) NOT NULL DEFAULT '0',
  `app_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `app_configurations`');
    }
};
// ######### FIN CAMBIO NELSON #########

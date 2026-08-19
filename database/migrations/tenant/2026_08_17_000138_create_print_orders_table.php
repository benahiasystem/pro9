<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `print_orders`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `name_printer`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Nombre de la impresora
 * - `status`: tinyint(4); NOT NULL; DEFAULT 0 — Estado de la orden de impresión: 0 = pendiente, 1 = procesando, 2 = impresa
 * - `pdf_b64`: longtext; NOT NULL; COLLATE utf8mb4_unicode_ci — Archivo PDF codificado en base64
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
CREATE TABLE `print_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name_printer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la impresora',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Estado de la orden de impresión: 0 = pendiente, 1 = procesando, 2 = impresa',
  `pdf_b64` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Archivo PDF codificado en base64',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `print_orders`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `company_accounts`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `subtotal_pen`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_pen`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `igv_pen`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `subtotal_usd`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_usd`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `igv_usd`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `exonerated`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `unaffected`: int(11); NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `company_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subtotal_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `igv_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `igv_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exonerated` int(11) DEFAULT NULL,
  `unaffected` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `company_accounts`');
    }
};

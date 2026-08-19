<?php
// ######### INICIO CAMBIO NELSON #########

/**
         * Run the migrations.
         *
         * @return void
         */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cat_periods`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `period`: char(1); NOT NULL; COLLATE utf8mb4_unicode_ci — Define si es dia, mes o año - D/M/Y
 * - `name`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Nombre del periodo
 * - `active`: tinyint(4); NOT NULL; DEFAULT 0 — Si esta activo
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
CREATE TABLE `cat_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `period` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Define si es dia, mes o año - D/M/Y',
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del periodo',
  `active` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Si esta activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_periods`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
         * Run the migrations.
         *
         * @return void
         */

/** Genera los requerimientos por proceso. */

/** Relaciona requerimientos y procesos */

/** Relaciona Etapas y procesos */

/** Relacion Expedientes y procesos */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documentary_files_requirements`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `name`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Nombre a mostrar
 * - `file`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — Define si tiene archivo
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
CREATE TABLE `documentary_files_requirements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre a mostrar',
  `file` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Define si tiene archivo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_files_requirements`');
    }
};
// ######### FIN CAMBIO NELSON #########

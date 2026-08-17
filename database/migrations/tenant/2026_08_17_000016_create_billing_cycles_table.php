<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `billing_cycles`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `date_time_start`: datetime; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `renew`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `quantity_documents`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `billing_cycles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_time_start` datetime NOT NULL,
  `renew` tinyint(1) NOT NULL DEFAULT '0',
  `quantity_documents` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `billing_cycles`');
    }
};

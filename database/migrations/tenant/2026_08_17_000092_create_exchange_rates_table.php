<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `exchange_rates`.
 *
 * Inventario de columnas:
 * - `date`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `sale_original`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `purchase_original`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `purchase`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `sale`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_original`: date; NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `exchange_rates` (
  `date` date NOT NULL,
  `sale_original` decimal(13,3) NOT NULL,
  `purchase_original` decimal(13,3) NOT NULL,
  `purchase` decimal(13,3) NOT NULL,
  `sale` decimal(13,3) NOT NULL,
  `date_original` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `exchange_rates`');
    }
};

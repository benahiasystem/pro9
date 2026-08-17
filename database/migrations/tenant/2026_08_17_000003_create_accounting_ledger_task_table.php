<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `accounting_ledger_task`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `month`: smallint(5) unsigned; NULL; DEFAULT 0 — Numero de mes
 * - `year`: mediumint(8) unsigned; NULL; DEFAULT 0 — Numero de mes
 * - `last_rum`: datetime; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `accounting_ledger_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `month` smallint(5) unsigned DEFAULT '0' COMMENT 'Numero de mes',
  `year` mediumint(8) unsigned DEFAULT '0' COMMENT 'Numero de mes',
  `last_rum` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `accounting_ledger_task`');
    }
};

<?php

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
 * Tabla: `cat_accounting_ledger_code_account`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `code_account`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Codigo de plan de cuenta
 * - `name`: longtext; NOT NULL; COLLATE utf8mb4_unicode_ci — Nombre de cuenta
 * - `disabled`: tinyint(3) unsigned; NULL; DEFAULT 0 — Permite realizar modificaciones
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
CREATE TABLE `cat_accounting_ledger_code_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code_account` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo de plan de cuenta',
  `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de cuenta',
  `disabled` tinyint(3) unsigned DEFAULT '0' COMMENT 'Permite realizar modificaciones',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_accounting_ledger_code_account`');
    }
};

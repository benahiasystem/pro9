<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_accounting_ledger_code_account` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `code_account` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo de plan de cuenta'
 * - `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de cuenta'
 * - `disabled` tinyint(3) unsigned DEFAULT '0' COMMENT 'Permite realizar modificaciones'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_accounting_ledger_code_account`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `accounting_ledger` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `month` smallint(5) unsigned DEFAULT '0' COMMENT 'Numero de mes'
 * - `year` mediumint(8) unsigned DEFAULT '0' COMMENT 'Numero de mes'
 * - `date_of_report` date DEFAULT NULL
 * - `code_account` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo de plan de cuenta'
 * - `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de cuenta'
 * - `last_month_total` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Debe ser el valor del total del mes pasado'
 * - `credits` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Créditos en el mes'
 * - `debs` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Debitos en el mes'
 * - `final_total` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Saldo Final de mes'
 * - `serialize_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'datos serialziados en bruto.'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `accounting_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `month` smallint(5) unsigned DEFAULT '0' COMMENT 'Numero de mes',
  `year` mediumint(8) unsigned DEFAULT '0' COMMENT 'Numero de mes',
  `date_of_report` date DEFAULT NULL,
  `code_account` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo de plan de cuenta',
  `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de cuenta',
  `last_month_total` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Debe ser el valor del total del mes pasado',
  `credits` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Créditos en el mes',
  `debs` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Debitos en el mes',
  `final_total` double(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Saldo Final de mes',
  `serialize_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'datos serialziados en bruto.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `accounting_ledger`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

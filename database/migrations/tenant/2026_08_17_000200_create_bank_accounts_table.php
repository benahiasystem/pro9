<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `bank_accounts` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `bank_id` int(10) unsigned NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `cci` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `status` tinyint(4) NOT NULL DEFAULT '1'
 * - `initial_balance` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `show_in_documents` tinyint(1) NOT NULL DEFAULT '1'
 * - `establishment_id` int(10) unsigned DEFAULT NULL
 * - `accounting_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_id` int(10) unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cci` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `initial_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `show_in_documents` tinyint(1) NOT NULL DEFAULT '1',
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `accounting_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_accounts_bank_id_foreign` (`bank_id`),
  KEY `bank_accounts_currency_type_id_foreign` (`currency_type_id`),
  KEY `bank_accounts_establishment_id_foreign` (`establishment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `bank_accounts`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

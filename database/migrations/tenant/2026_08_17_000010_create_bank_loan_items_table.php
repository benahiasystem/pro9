<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `bank_loan_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `bank_loan_id` int(10) unsigned NOT NULL
 * - `description` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total` decimal(30,12) DEFAULT '0.00'
 * - `total_interest` decimal(30,12) DEFAULT '0.00'
 * - `total_ingress` decimal(30,12) DEFAULT '0.00'
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_loan_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_loan_id` int(10) unsigned NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(30,12) DEFAULT '0.00',
  `total_interest` decimal(30,12) DEFAULT '0.00',
  `total_ingress` decimal(30,12) DEFAULT '0.00',
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `bank_loan_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

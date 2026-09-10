<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `bank_loan_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `bank_loan_id` int(10) unsigned NOT NULL
 * - `date_of_payment` date NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `has_card` tinyint(1) NOT NULL DEFAULT '0'
 * - `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment` decimal(12,2) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `bank_loan_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_loan_id` int(10) unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `bank_loan_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

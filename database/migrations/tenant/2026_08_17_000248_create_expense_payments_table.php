<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `expense_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `expense_id` int(10) unsigned NOT NULL
 * - `date_of_payment` date NOT NULL
 * - `expense_method_type_id` int(10) unsigned NOT NULL
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
CREATE TABLE `expense_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_id` int(10) unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `expense_method_type_id` int(10) unsigned NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_payments_expense_id_foreign` (`expense_id`),
  KEY `expense_payments_card_brand_id_foreign` (`card_brand_id`),
  KEY `expense_payments_expense_method_type_id_foreign` (`expense_method_type_id`),
  KEY `expense_payments_date_of_payment_index` (`date_of_payment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `expense_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

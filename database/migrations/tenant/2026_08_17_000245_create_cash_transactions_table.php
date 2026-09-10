<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cash_transactions` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `cash_id` int(10) unsigned NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date` date NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment` decimal(14,4) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_id` int(10) unsigned NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` decimal(14,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_transactions_cash_id_foreign` (`cash_id`),
  KEY `cash_transactions_payment_method_type_id_foreign` (`payment_method_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cash_transactions`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

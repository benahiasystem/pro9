<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `account_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `date_of_payment` date NOT NULL
 * - `date_of_payment_real` date DEFAULT NULL
 * - `reference_id` int(10) unsigned DEFAULT NULL
 * - `payment_method_type_id` int(10) unsigned DEFAULT NULL
 * - `has_card` tinyint(1) NOT NULL DEFAULT '0'
 * - `card_brand_id` int(10) unsigned DEFAULT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment` decimal(12,2) NOT NULL
 * - `state` tinyint(1) NOT NULL DEFAULT '0'
 * - `reference_payment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `account_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_of_payment` date NOT NULL,
  `date_of_payment_real` date DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `payment_method_type_id` int(10) unsigned DEFAULT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` int(10) unsigned DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT '0',
  `reference_payment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `account_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

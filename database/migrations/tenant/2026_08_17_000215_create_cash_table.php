<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cash` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `user_id` int(10) unsigned NOT NULL
 * - `date_opening` date NOT NULL
 * - `time_opening` time NOT NULL
 * - `date_closed` date DEFAULT NULL
 * - `time_closed` time DEFAULT NULL
 * - `beginning_balance` decimal(12,4) NOT NULL DEFAULT '0.0000'
 * - `final_balance` decimal(12,4) NOT NULL DEFAULT '0.0000'
 * - `income` decimal(12,4) NOT NULL DEFAULT '0.0000'
 * - `state` tinyint(1) NOT NULL DEFAULT '0'
 * - `reference_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `apply_restaurant` tinyint(1) NOT NULL DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `date_opening` date NOT NULL,
  `time_opening` time NOT NULL,
  `date_closed` date DEFAULT NULL,
  `time_closed` time DEFAULT NULL,
  `beginning_balance` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `final_balance` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `income` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `state` tinyint(1) NOT NULL DEFAULT '0',
  `reference_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `apply_restaurant` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `cash_user_id_foreign` (`user_id`),
  KEY `cash_date_opening_index` (`date_opening`),
  KEY `cash_income_index` (`income`),
  KEY `cash_state_index` (`state`),
  KEY `cash_reference_number_index` (`reference_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cash`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

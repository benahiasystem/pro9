<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `purchase_supplies` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `purchase_id` int(10) unsigned NOT NULL
 * - `supply_id` int(10) unsigned NOT NULL
 * - `quantity` decimal(12,2) NOT NULL
 * - `cost` decimal(12,2) NOT NULL
 * - `affectation_igv_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `purchase_supplies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `supply_id` int(10) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `cost` decimal(12,2) NOT NULL,
  `affectation_igv_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_supplies_purchase_id_foreign` (`purchase_id`),
  KEY `purchase_supplies_supply_id_foreign` (`supply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `purchase_supplies`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `inventory_kardex` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `date_of_issue` date NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `inventory_kardexable_id` int(10) unsigned NOT NULL
 * - `inventory_kardexable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `warehouse_id` int(10) unsigned NOT NULL
 * - `quantity` decimal(12,4) NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventory_kardex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_of_issue` date NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `inventory_kardexable_id` int(10) unsigned NOT NULL,
  `inventory_kardexable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_kardex_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_kardex_item_wh_date_index` (`item_id`,`warehouse_id`,`date_of_issue`),
  KEY `inventory_kardex_kardexable_index` (`inventory_kardexable_type`,`inventory_kardexable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `inventory_kardex`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

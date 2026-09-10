<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `item_sets` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned NOT NULL
 * - `individual_item_id` int(10) unsigned NOT NULL
 * - `quantity` decimal(12,4) NOT NULL DEFAULT '1.0000'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `item_sets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `individual_item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_sets_item_id_foreign` (`item_id`),
  KEY `item_sets_individual_item_id_foreign` (`individual_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_sets`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

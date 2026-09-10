<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `mill_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned DEFAULT '0'
 * - `mill_id` int(10) unsigned DEFAULT '0'
 * - `height_to_mill` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso de entrada'
 * - `total_height` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso dle insumo '
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `item` json NOT NULL
 * - `quantity` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso dle insumo '
 * - `item_extra_data` json DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `mill_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned DEFAULT '0',
  `mill_id` int(10) unsigned DEFAULT '0',
  `height_to_mill` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso de entrada',
  `total_height` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso dle insumo ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,3) DEFAULT '0.000' COMMENT 'Peso dle insumo ',
  `item_extra_data` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `mill_items`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

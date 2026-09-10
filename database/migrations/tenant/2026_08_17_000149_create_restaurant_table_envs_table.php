<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `restaurant_table_envs` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL
 * - `tables_quantity` int(11) NOT NULL
 * - `is_delivery` tinyint(1) NOT NULL DEFAULT '0'
 * - `is_takeaway` tinyint(1) NOT NULL DEFAULT '0'
 * - `can_edit` tinyint(1) NOT NULL DEFAULT '1'
 * - `can_deactivate` tinyint(1) NOT NULL DEFAULT '1'
 * - `can_delete` tinyint(1) NOT NULL DEFAULT '1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `restaurant_table_envs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `tables_quantity` int(11) NOT NULL,
  `is_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `is_takeaway` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit` tinyint(1) NOT NULL DEFAULT '1',
  `can_deactivate` tinyint(1) NOT NULL DEFAULT '1',
  `can_delete` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_table_envs`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `dispatch_addresses` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `person_id` int(10) unsigned NOT NULL
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `location_id` json NOT NULL
 * - `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000'
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` json NOT NULL,
  `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispatch_address_unique` (`person_id`,`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatch_addresses`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

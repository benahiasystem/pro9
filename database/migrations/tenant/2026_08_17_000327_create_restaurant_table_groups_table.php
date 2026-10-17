<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `restaurant_table_groups` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `main_table_id` int(10) unsigned DEFAULT NULL
 * - `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open'
 * - `opening_date` datetime DEFAULT NULL
 * - `total` decimal(30,12) NOT NULL DEFAULT '0.00'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `restaurant_table_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `main_table_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `opening_date` datetime DEFAULT NULL,
  `total` decimal(30,12) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL COMMENT 'Exact decimal fractions v1',
  PRIMARY KEY (`id`),
  KEY `restaurant_table_groups_main_table_id_foreign` (`main_table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_table_groups`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

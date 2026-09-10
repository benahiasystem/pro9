<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `billing_cycles` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `date_time_start` datetime NOT NULL
 * - `renew` tinyint(1) NOT NULL DEFAULT '0'
 * - `quantity_documents` int(11) NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `billing_cycles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_time_start` datetime NOT NULL,
  `renew` tinyint(1) NOT NULL DEFAULT '0',
  `quantity_documents` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `billing_cycles`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

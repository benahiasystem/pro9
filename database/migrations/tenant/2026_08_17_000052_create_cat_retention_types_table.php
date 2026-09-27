<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Tipos de operación ISLR del manual SENIAT 3.1 (2014), para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL, tipo de operación
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `abbreviation` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_retention_types` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_retention_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

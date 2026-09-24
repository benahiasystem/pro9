<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial del catálogo HKA `cat_providers_types` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` tinyint unsigned NOT NULL, clave primaria local
 * - `code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL, código HKA
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_providers_types` (
  `id` tinyint unsigned NOT NULL,
  `code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cat_providers_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_providers_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

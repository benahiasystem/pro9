<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `districts` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` char(6) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `province_id` char(4) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL DEFAULT '1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `districts` (
  `id` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_id` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `districts_province_id_foreign` (`province_id`),
  KEY `districts_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `districts`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

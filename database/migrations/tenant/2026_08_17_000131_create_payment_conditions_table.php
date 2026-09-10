<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `payment_conditions` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `days` int(11) NOT NULL DEFAULT '0'
 * - `is_locked` tinyint(1) NOT NULL DEFAULT '0'
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_conditions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `days` int(11) NOT NULL DEFAULT '0',
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `payment_conditions_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_conditions`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_operation_types` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL
 * - `exportation` tinyint(1) NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_operation_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `exportation` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_operation_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_operation_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

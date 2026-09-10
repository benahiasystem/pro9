<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_affectation_igv_types` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL
 * - `exportation` tinyint(1) DEFAULT NULL
 * - `free` tinyint(1) DEFAULT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_affectation_igv_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `exportation` tinyint(1) DEFAULT NULL,
  `free` tinyint(1) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `cat_affectation_igv_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_affectation_igv_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

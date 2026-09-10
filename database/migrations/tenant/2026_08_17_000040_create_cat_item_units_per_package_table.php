<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_item_units_per_package` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de unidades por paquete'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_item_units_per_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de unidades por paquete',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_item_units_per_package`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

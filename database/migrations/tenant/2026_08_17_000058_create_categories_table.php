<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `categories` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `cuenta_compra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `cuenta_venta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `cuenta_compra_devolucion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `cuenta_venta_devolucion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cuenta_compra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuenta_venta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuenta_compra_devolucion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuenta_venta_devolucion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categories_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `categories`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

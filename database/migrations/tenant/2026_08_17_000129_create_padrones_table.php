<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `padrones` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `ruc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `nombre_razon_social` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `estado_contribuyente` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `condicion_domicilio` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `ubigeo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `tipo_via` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `nombre_via` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `codigo_zona` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `tipo_zona` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `numero` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `interior` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `lote` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `departamento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `manzana` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `kilometro` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `status` tinyint(4) NOT NULL DEFAULT '1'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `padrones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ruc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_razon_social` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_contribuyente` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condicion_domicilio` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubigeo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_via` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_via` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_zona` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_zona` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interior` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lote` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departamento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manzana` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kilometro` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `padrones_ruc_index` (`ruc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `padrones`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

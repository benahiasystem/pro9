<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `padrones`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `ruc`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `nombre_razon_social`: varchar(155); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `estado_contribuyente`: varchar(100); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `condicion_domicilio`: varchar(100); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `ubigeo`: varchar(50); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `tipo_via`: varchar(20); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `nombre_via`: varchar(50); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `codigo_zona`: varchar(155); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `tipo_zona`: varchar(20); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `numero`: varchar(155); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `interior`: varchar(50); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `lote`: varchar(20); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `departamento`: varchar(100); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `manzana`: varchar(20); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `kilometro`: varchar(20); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `status`: tinyint(4); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
// ######### FIN CAMBIO NELSON #########

<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `series_device_groups`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — El grupo vive en un establecimiento
 * - `name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Nombre del grupo: ej. Caja 1
 * - `module_value`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Modulo asociado (uno solo). Catalogo extensible
 * - `bound_device_name`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Nombre del equipo vinculado (clave de vinculo)
 * - `bound_user_id`: int(10) unsigned; NULL — Usuario que vinculo el grupo
 * - `bound_at`: timestamp; NULL — Fecha de vinculo al equipo
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
CREATE TABLE `series_device_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `establishment_id` int(10) unsigned NOT NULL COMMENT 'El grupo vive en un establecimiento',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del grupo: ej. Caja 1',
  `module_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Modulo asociado (uno solo). Catalogo extensible',
  `bound_device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del equipo vinculado (clave de vinculo)',
  `bound_user_id` int(10) unsigned DEFAULT NULL COMMENT 'Usuario que vinculo el grupo',
  `bound_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de vinculo al equipo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `series_device_groups_establishment_id_foreign` (`establishment_id`),
  KEY `series_device_groups_bound_device_name_index` (`bound_device_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `series_device_groups`');
    }
};

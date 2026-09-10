<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `template_columns_config` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `template_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Plantilla_personalizable'
 * - `columns_config` json DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `template_columns_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `establishment_id` int(10) unsigned NOT NULL,
  `template_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Plantilla_personalizable',
  `columns_config` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_columns_config_establishment_id_template_name_unique` (`establishment_id`,`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `template_columns_config`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

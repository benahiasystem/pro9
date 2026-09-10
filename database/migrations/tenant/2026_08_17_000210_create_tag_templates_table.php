<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `tag_templates` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `width` double(8,2) NOT NULL
 * - `height` double(8,2) NOT NULL
 * - `establishment_id` int(10) unsigned NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `is_default` tinyint(1) NOT NULL DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `tag_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `width` double(8,2) NOT NULL,
  `height` double(8,2) NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `tag_templates_establishment_id_foreign` (`establishment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `tag_templates`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

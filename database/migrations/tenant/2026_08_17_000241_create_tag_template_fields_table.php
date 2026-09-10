<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `tag_template_fields` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `column` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `x` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `y` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `width` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `height` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `style` json DEFAULT NULL
 * - `barcode` json DEFAULT NULL
 * - `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `html_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `has_image` tinyint(1) NOT NULL DEFAULT '0'
 * - `tag_template_id` int(10) unsigned NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `tag_template_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `column` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `x` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `y` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `height` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `style` json DEFAULT NULL,
  `barcode` json DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_image` tinyint(1) NOT NULL DEFAULT '0',
  `tag_template_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag_template_fields_tag_template_id_foreign` (`tag_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `tag_template_fields`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

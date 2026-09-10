<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `app_configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `show_image_item` tinyint(1) NOT NULL DEFAULT '1'
 * - `print_format_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ticket'
 * - `card_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multicolored'
 * - `theme_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue'
 * - `header_waves` tinyint(1) NOT NULL DEFAULT '0'
 * - `direct_send_documents_whatsapp` tinyint(1) NOT NULL DEFAULT '0'
 * - `primary_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#020F3C' COMMENT 'Color principal para la nueva app movil - hexadecimal'
 * - `direct_print` tinyint(1) NOT NULL DEFAULT '0'
 * - `app_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `app_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `show_image_item` tinyint(1) NOT NULL DEFAULT '1',
  `print_format_pdf` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ticket',
  `card_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multicolored',
  `theme_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `header_waves` tinyint(1) NOT NULL DEFAULT '0',
  `direct_send_documents_whatsapp` tinyint(1) NOT NULL DEFAULT '0',
  `primary_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#020F3C' COMMENT 'Color principal para la nueva app movil - hexadecimal',
  `direct_print` tinyint(1) NOT NULL DEFAULT '0',
  `app_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `app_configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

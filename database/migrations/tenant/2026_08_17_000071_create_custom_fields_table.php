<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `custom_fields` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `required` tinyint(1) NOT NULL DEFAULT '0'
 * - `options` json DEFAULT NULL
 * - `order` int(11) NOT NULL DEFAULT '0'
 * - `enabled_for_documents` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_for_sale_notes` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_for_dispatches` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_for_order_notes` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_for_quotations` tinyint(1) NOT NULL DEFAULT '0'
 * - `show_in_pdf` tinyint(1) NOT NULL DEFAULT '1'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `custom_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `options` json DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT '0',
  `enabled_for_documents` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_for_sale_notes` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_for_dispatches` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_for_order_notes` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_for_quotations` tinyint(1) NOT NULL DEFAULT '0',
  `show_in_pdf` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `custom_fields_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `custom_fields`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

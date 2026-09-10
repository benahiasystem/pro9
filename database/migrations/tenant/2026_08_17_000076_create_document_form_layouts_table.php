<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `document_form_layouts` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `variant` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `pinned_fields` json DEFAULT NULL
 * - `updated_by_user_id` bigint(20) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_form_layouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `variant` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pinned_fields` json DEFAULT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_form_layouts_variant_unique` (`variant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `document_form_layouts`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

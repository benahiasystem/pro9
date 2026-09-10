<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `sale_note_migration` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `sale_notes_id` int(10) unsigned NOT NULL
 * - `user_id` int(10) unsigned NOT NULL
 * - `success` tinyint(3) unsigned NOT NULL DEFAULT '0'
 * - `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `remote_id` int(10) unsigned NOT NULL DEFAULT '0'
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `data` longtext COLLATE utf8mb4_unicode_ci
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `sale_note_migration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_notes_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `success` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remote_id` int(10) unsigned NOT NULL DEFAULT '0',
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `sale_note_migration`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `person_types` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `description` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `price_label_id` int(10) unsigned DEFAULT NULL
 * - `enabled_description_person_type` tinyint(1) NOT NULL DEFAULT '0'
 * - `description_person_type` text COLLATE utf8mb4_unicode_ci
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `person_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `price_label_id` int(10) unsigned DEFAULT NULL,
  `enabled_description_person_type` tinyint(1) NOT NULL DEFAULT '0',
  `description_person_type` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `person_types_price_label_id_foreign` (`price_label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `person_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

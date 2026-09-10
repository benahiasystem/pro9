<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `dispatch_sale_notes` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `sale_note_id` int(10) unsigned NOT NULL
 * - `date_dispatch` date DEFAULT NULL
 * - `time_dispatch` time DEFAULT NULL
 * - `person_pick` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `person_dispatch` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `status` tinyint(1) DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_sale_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_note_id` int(10) unsigned NOT NULL,
  `date_dispatch` date DEFAULT NULL,
  `time_dispatch` time DEFAULT NULL,
  `person_pick` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_dispatch` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispatch_sale_notes_sale_note_id_foreign` (`sale_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatch_sale_notes`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

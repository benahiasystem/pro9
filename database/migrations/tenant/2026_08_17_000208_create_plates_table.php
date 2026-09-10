<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `plates` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `person_id` int(10) unsigned NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `plates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `person_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `plates_person_id_foreign` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `plates`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

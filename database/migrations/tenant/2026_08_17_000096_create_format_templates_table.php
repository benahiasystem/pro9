<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `format_templates` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `formats` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `urls` json DEFAULT NULL
 * - `is_custom_ticket` tinyint(1) NOT NULL DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `format_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `formats` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urls` json DEFAULT NULL,
  `is_custom_ticket` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `format_templates`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

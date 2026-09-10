<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `rel_user_to_documentary_offices` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `active` tinyint(3) unsigned NOT NULL DEFAULT '0'
 * - `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'usuario asociado'
 * - `documentary_office_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'etapa asociada'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `rel_user_to_documentary_offices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'usuario asociado',
  `documentary_office_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'etapa asociada',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `rel_user_to_documentary_offices`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

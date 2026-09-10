<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `email_send_log` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `relation_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de modelo'
 * - `type` int(10) unsigned DEFAULT '0' COMMENT 'Tipo de relacion'
 * - `relation_model` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Modelo a relacion'
 * - `file_line` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Archivo qu elo llama'
 * - `sendit` tinyint(3) unsigned DEFAULT '1' COMMENT 'Booleano para envio de correo'
 * - `email` text COLLATE utf8mb4_unicode_ci COMMENT 'Correo de destino'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `email_send_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `relation_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de modelo',
  `type` int(10) unsigned DEFAULT '0' COMMENT 'Tipo de relacion',
  `relation_model` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Modelo a relacion',
  `file_line` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Archivo qu elo llama',
  `sendit` tinyint(3) unsigned DEFAULT '1' COMMENT 'Booleano para envio de correo',
  `email` text COLLATE utf8mb4_unicode_ci COMMENT 'Correo de destino',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `email_send_log`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

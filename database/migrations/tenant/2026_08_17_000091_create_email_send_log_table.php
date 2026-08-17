<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `email_send_log`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `relation_id`: int(10) unsigned; NULL; DEFAULT 0 — Id de modelo
 * - `type`: int(10) unsigned; NULL; DEFAULT 0 — Tipo de relacion
 * - `relation_model`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Modelo a relacion
 * - `file_line`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Archivo qu elo llama
 * - `sendit`: tinyint(3) unsigned; NULL; DEFAULT 1 — Booleano para envio de correo
 * - `email`: text; NULL; COLLATE utf8mb4_unicode_ci — Correo de destino
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

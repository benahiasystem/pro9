<?php
// ######### INICIO CAMBIO NELSON #########

/**
         * Run the migrations.
         *
         * @return void
         */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `full_suscription_user_data`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `person_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `discord_user`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `slack_channel`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `discord_channel`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `gitlab_user`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `full_suscription_user_data` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned DEFAULT '0',
  `discord_user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slack_channel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_channel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gitlab_user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `full_suscription_user_data`');
    }
};
// ######### FIN CAMBIO NELSON #########

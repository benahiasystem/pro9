<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cat_digemid`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Id de la tabla de item
 * - `cod_digemid`: longtext; NOT NULL; COLLATE utf8mb4_unicode_ci — Codigo digmid
 * - `nom_prod`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Nombre segun digemid
 * - `concent`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Dosificacion segun digemid
 * - `nom_form_farm`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `nom_form_farm_simplif`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `presentac`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `fracciones`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `fec_vcto_reg_sanitario`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `num_reg_san`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `nom_titular`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `prices`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `max_prices`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `active`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `last_update`: timestamp; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `cat_digemid` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL COMMENT 'Id de la tabla de item',
  `cod_digemid` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo digmid',
  `nom_prod` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre segun digemid',
  `concent` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Dosificacion segun digemid',
  `nom_form_farm` longtext COLLATE utf8mb4_unicode_ci,
  `nom_form_farm_simplif` longtext COLLATE utf8mb4_unicode_ci,
  `presentac` longtext COLLATE utf8mb4_unicode_ci,
  `fracciones` longtext COLLATE utf8mb4_unicode_ci,
  `fec_vcto_reg_sanitario` longtext COLLATE utf8mb4_unicode_ci,
  `num_reg_san` longtext COLLATE utf8mb4_unicode_ci,
  `nom_titular` longtext COLLATE utf8mb4_unicode_ci,
  `prices` longtext COLLATE utf8mb4_unicode_ci,
  `max_prices` int(10) unsigned DEFAULT '0',
  `active` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `last_update` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_digemid`');
    }
};

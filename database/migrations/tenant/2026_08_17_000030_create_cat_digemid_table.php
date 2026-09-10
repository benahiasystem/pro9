<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_digemid` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `item_id` int(10) unsigned NOT NULL COMMENT 'Id de la tabla de item'
 * - `cod_digemid` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codigo digmid'
 * - `nom_prod` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre segun digemid'
 * - `concent` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Dosificacion segun digemid'
 * - `nom_form_farm` longtext COLLATE utf8mb4_unicode_ci
 * - `nom_form_farm_simplif` longtext COLLATE utf8mb4_unicode_ci
 * - `presentac` longtext COLLATE utf8mb4_unicode_ci
 * - `fracciones` longtext COLLATE utf8mb4_unicode_ci
 * - `fec_vcto_reg_sanitario` longtext COLLATE utf8mb4_unicode_ci
 * - `num_reg_san` longtext COLLATE utf8mb4_unicode_ci
 * - `nom_titular` longtext COLLATE utf8mb4_unicode_ci
 * - `prices` longtext COLLATE utf8mb4_unicode_ci
 * - `max_prices` int(10) unsigned DEFAULT '0'
 * - `active` tinyint(3) unsigned NOT NULL DEFAULT '0'
 * - `last_update` timestamp NULL DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
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
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

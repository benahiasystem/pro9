<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `status_claims` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `description` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#909399'
 * - `sort_order` smallint(5) unsigned NOT NULL DEFAULT '0'
 * - `is_initial` tinyint(1) NOT NULL DEFAULT '0'
 * - `is_final` tinyint(1) NOT NULL DEFAULT '0'
 * - `action_send_email` tinyint(1) NOT NULL DEFAULT '0'
 * - `assigned_user_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `status_claims` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#909399',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `is_final` tinyint(1) NOT NULL DEFAULT '0',
  `action_send_email` tinyint(1) NOT NULL DEFAULT '0',
  `assigned_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `status_claims`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

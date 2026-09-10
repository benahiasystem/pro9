<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `fiscal_configuration_audits` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `company_id` int(10) unsigned NOT NULL
 * - `actor_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `actor_id` int(10) unsigned NOT NULL
 * - `changed_fields` text COLLATE utf8mb4_unicode_ci NOT NULL
 * - `fiscal_emission_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `fiscal_configuration_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `actor_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` int(10) unsigned NOT NULL,
  `changed_fields` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_emission_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fiscal_configuration_audits_company_id_foreign` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `fiscal_configuration_audits`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

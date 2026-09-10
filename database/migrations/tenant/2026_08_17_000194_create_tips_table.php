<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `tips` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date` date NOT NULL COMMENT 'Fecha de registro'
 * - `origin_date_of_issue` date NOT NULL COMMENT 'Fecha del documento origen de la propina'
 * - `origin_id` int(11) NOT NULL
 * - `origin_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `worker_full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total` decimal(12,2) NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `tips` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL COMMENT 'Fecha de registro',
  `origin_date_of_issue` date NOT NULL COMMENT 'Fecha del documento origen de la propina',
  `origin_id` int(11) NOT NULL,
  `origin_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `worker_full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `origin_index` (`origin_id`,`origin_type`),
  KEY `tips_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `tips_date_index` (`date`),
  KEY `tips_origin_date_of_issue_index` (`origin_date_of_issue`),
  KEY `tips_worker_full_name_index` (`worker_full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `tips`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

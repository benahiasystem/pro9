<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `income`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `income_type_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `income_reason_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `state_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `external_id`: char(36); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_of_issue`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `exchange_rate_sale`: decimal(13,3); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `income` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `income_type_id` int(10) unsigned NOT NULL,
  `income_reason_id` int(10) unsigned NOT NULL,
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `customer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `exchange_rate_sale` decimal(13,3) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `income_filename_unique` (`filename`),
  KEY `income_user_id_foreign` (`user_id`),
  KEY `income_establishment_id_foreign` (`establishment_id`),
  KEY `income_income_type_id_foreign` (`income_type_id`),
  KEY `income_income_reason_id_foreign` (`income_reason_id`),
  KEY `income_state_type_id_foreign` (`state_type_id`),
  KEY `income_soap_type_id_foreign` (`soap_type_id`),
  KEY `income_currency_type_id_foreign` (`currency_type_id`),
  KEY `income_number_index` (`number`),
  KEY `income_date_of_issue_index` (`date_of_issue`),
  KEY `income_external_id_index` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `income`');
    }
};
// ######### FIN CAMBIO NELSON #########

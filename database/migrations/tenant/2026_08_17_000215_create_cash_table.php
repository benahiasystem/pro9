<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `cash`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_opening`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_opening`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_closed`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `time_closed`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `beginning_balance`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `final_balance`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `income`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `state`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `reference_number`: varchar(20); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `apply_restaurant`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cash` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `date_opening` date NOT NULL,
  `time_opening` time NOT NULL,
  `date_closed` date DEFAULT NULL,
  `time_closed` time DEFAULT NULL,
  `beginning_balance` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `final_balance` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `income` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `state` tinyint(1) NOT NULL DEFAULT '0',
  `reference_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `apply_restaurant` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `cash_user_id_foreign` (`user_id`),
  KEY `cash_date_opening_index` (`date_opening`),
  KEY `cash_income_index` (`income`),
  KEY `cash_state_index` (`state`),
  KEY `cash_reference_number_index` (`reference_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cash`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `bank_loans`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `bank_loan_type_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `bank_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `bank_account_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `external_id`: char(36); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time_of_issue`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `bank`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `exchange_rate_sale`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_interest`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_ingress`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `state_type_id`: char(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `bank_loans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `bank_loan_type_id` int(10) unsigned NOT NULL,
  `establishment_id` int(10) unsigned NOT NULL,
  `bank_id` int(10) unsigned NOT NULL,
  `bank_account_id` int(10) unsigned NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int(11) NOT NULL,
  `date_of_issue` date NOT NULL,
  `time_of_issue` time NOT NULL,
  `bank` json NOT NULL,
  `exchange_rate_sale` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) DEFAULT '0.00',
  `total_interest` decimal(12,2) DEFAULT '0.00',
  `total_ingress` decimal(12,2) DEFAULT '0.00',
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_type_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `bank_loans`');
    }
};
// ######### FIN CAMBIO NELSON #########

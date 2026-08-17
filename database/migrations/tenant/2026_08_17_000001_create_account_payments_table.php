<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `account_payments`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `date_of_payment`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_payment_real`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `reference_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `has_card`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `card_brand_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `reference`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `state`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `reference_payment`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `account_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_of_payment` date NOT NULL,
  `date_of_payment_real` date DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `payment_method_type_id` int(10) unsigned DEFAULT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `card_brand_id` int(10) unsigned DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT '0',
  `reference_payment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `account_payments`');
    }
};

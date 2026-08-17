<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `hotel_rents`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `customer_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `customer`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `notes`: varchar(250); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `towels`: int(11); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `hotel_room_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `hotel_rate_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `duration`: int(11); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `quantity_persons`: int(11); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `data_persons`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `input_date`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `input_time`: varchar(8); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_status`: varchar(10); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `output_date`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `output_time`: varchar(8); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `arrears`: int(11); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `status`: varchar(10); NOT NULL; DEFAULT INICIADO; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `hotel_rents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `customer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `towels` int(11) NOT NULL DEFAULT '1',
  `hotel_room_id` int(10) unsigned NOT NULL,
  `hotel_rate_id` int(10) unsigned DEFAULT NULL,
  `duration` int(11) NOT NULL DEFAULT '1',
  `quantity_persons` int(11) NOT NULL DEFAULT '1',
  `data_persons` json DEFAULT NULL,
  `input_date` date DEFAULT NULL,
  `input_time` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_date` date NOT NULL,
  `output_time` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arrears` int(11) NOT NULL DEFAULT '0',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INICIADO',
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hotel_rents_hotel_rate_id_foreign` (`hotel_rate_id`),
  KEY `hotel_rents_establishment_id_foreign` (`establishment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_rents`');
    }
};

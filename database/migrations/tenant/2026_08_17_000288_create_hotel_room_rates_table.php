<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `hotel_room_rates`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `hotel_room_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `hotel_rate_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price`: double; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `hotel_room_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hotel_room_id` int(10) unsigned NOT NULL,
  `hotel_rate_id` int(10) unsigned NOT NULL,
  `price` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hotel_room_rates_hotel_room_id_foreign` (`hotel_room_id`),
  KEY `hotel_room_rates_hotel_rate_id_foreign` (`hotel_rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_room_rates`');
    }
};
// ######### FIN CAMBIO NELSON #########

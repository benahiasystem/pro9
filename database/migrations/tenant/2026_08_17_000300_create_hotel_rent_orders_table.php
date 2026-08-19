<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `hotel_rent_orders`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `hotel_rent_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `order_number`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `order_status`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `hotel_rent_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hotel_rent_id` int(10) unsigned NOT NULL,
  `order_number` int(11) NOT NULL,
  `order_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hotel_rent_orders_hotel_rent_id_foreign` (`hotel_rent_id`),
  KEY `hotel_rent_orders_sale_note_id_foreign` (`sale_note_id`),
  KEY `hotel_rent_orders_establishment_id_foreign` (`establishment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_rent_orders`');
    }
};
// ######### FIN CAMBIO NELSON #########

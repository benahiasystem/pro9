<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `hotel_rent_item_payments`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `hotel_rent_item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_payment`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reference`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `change`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `payment`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `hotel_rent_item_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hotel_rent_item_id` int(10) unsigned NOT NULL,
  `date_of_payment` date NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change` decimal(12,2) DEFAULT NULL,
  `payment` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `hotel_rent_item_payments_hotel_rent_item_id_foreign` (`hotel_rent_item_id`),
  KEY `hotel_rent_item_payments_payment_method_type_id_foreign` (`payment_method_type_id`),
  KEY `hotel_rent_item_payments_date_of_payment_index` (`date_of_payment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `hotel_rent_item_payments`');
    }
};
// ######### FIN CAMBIO NELSON #########

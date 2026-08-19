<?php
// ######### INICIO CAMBIO NELSON #########

/**
         * Run the migrations.
         *
         * @return void
         */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `configuration_mi_tienda_pe`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `series_order_note_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `series_document_ft_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `series_document_bt_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `payment_destination_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `autogenerate`: tinyint(3) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `configuration_mi_tienda_pe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `establishment_id` int(10) unsigned DEFAULT '1',
  `series_order_note_id` int(10) unsigned DEFAULT '0',
  `series_document_ft_id` int(10) unsigned DEFAULT '0',
  `series_document_bt_id` int(10) unsigned DEFAULT '0',
  `user_id` int(10) unsigned DEFAULT '0',
  `payment_destination_id` int(10) unsigned DEFAULT '0',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `autogenerate` tinyint(3) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `configuration_mi_tienda_pe`');
    }
};
// ######### FIN CAMBIO NELSON #########

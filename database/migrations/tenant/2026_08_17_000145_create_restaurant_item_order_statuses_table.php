<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `restaurant_item_order_statuses`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `table_id`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `note`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `status`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `status_description`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `restaurant_item_order_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item` json DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL,
  `status_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_item_order_statuses`');
    }
};
// ######### FIN CAMBIO NELSON #########

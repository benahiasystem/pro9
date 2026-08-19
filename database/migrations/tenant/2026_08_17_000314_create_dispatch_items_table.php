<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `dispatch_items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `dispatch_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `name_product_pdf`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `additional_data`: json; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dispatch_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci,
  `additional_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispatch_items_dispatch_id_foreign` (`dispatch_id`),
  KEY `dispatch_items_item_id_foreign` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatch_items`');
    }
};
// ######### FIN CAMBIO NELSON #########

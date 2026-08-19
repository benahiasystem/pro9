<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `inventories`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `type`: enum('1','2','3'); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `detail`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `warehouse_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `warehouse_destination_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `inventory_transaction_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `lot_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `inventories_transfer_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `comments`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `guide_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `system_stock`: decimal(12,4); NULL — Sin comentario definido en el esquema fuente.
 * - `real_stock`: decimal(12,4); NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `inventories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `warehouse_destination_id` int(10) unsigned DEFAULT NULL,
  `inventory_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventories_transfer_id` int(10) unsigned DEFAULT NULL,
  `comments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_issue` date DEFAULT NULL,
  `guide_id` int(10) unsigned DEFAULT NULL,
  `system_stock` decimal(12,4) DEFAULT NULL,
  `real_stock` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventories_item_id_foreign` (`item_id`),
  KEY `inventories_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventories_inventory_transaction_id_foreign` (`inventory_transaction_id`),
  KEY `inventories_inventories_transfer_id_foreign` (`inventories_transfer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `inventories`');
    }
};
// ######### FIN CAMBIO NELSON #########

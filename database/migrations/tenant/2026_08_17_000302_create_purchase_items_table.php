<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `purchase_items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `purchase_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item_lot_group_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `lot_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `unit_value`: decimal(16,6); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_due`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `affectation_igv_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_base_igv`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `percentage_igv`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_igv`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `system_isc_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_base_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_isc`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_other_taxes`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_other_taxes`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_other_taxes`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unit_price`: decimal(16,6); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_value`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_charge`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_discount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `attributes`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `discounts`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `charges`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `warehouse_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `name_product_pdf`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `update_purchase_price`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `item_lot_group_id` int(10) unsigned DEFAULT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `unit_value` decimal(16,6) NOT NULL,
  `date_of_due` date DEFAULT NULL,
  `affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_base_igv` decimal(12,2) NOT NULL,
  `percentage_igv` decimal(12,2) NOT NULL,
  `total_igv` decimal(12,2) NOT NULL,
  `system_isc_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_base_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
  `percentage_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_isc` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_base_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `percentage_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_other_taxes` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_taxes` decimal(12,2) NOT NULL,
  `price_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(16,6) NOT NULL,
  `total_value` decimal(12,2) NOT NULL,
  `total_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `attributes` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `charges` json DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci,
  `update_purchase_price` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `purchase_items_purchase_id_foreign` (`purchase_id`),
  KEY `purchase_items_item_id_foreign` (`item_id`),
  KEY `purchase_items_affectation_igv_type_id_foreign` (`affectation_igv_type_id`),
  KEY `purchase_items_system_isc_type_id_foreign` (`system_isc_type_id`),
  KEY `purchase_items_price_type_id_foreign` (`price_type_id`),
  KEY `purchase_items_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_items_item_lot_group_id_index` (`item_lot_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `purchase_items`');
    }
};
// ######### FIN CAMBIO NELSON #########

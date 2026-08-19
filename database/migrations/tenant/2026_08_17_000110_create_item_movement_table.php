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
 * Tabla: `item_movement`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con item
 * - `quantity`: decimal(12,4); NULL; DEFAULT 0.0000 — Cantidad de venta del item
 * - `date_of_movement`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `countable`: tinyint(3) unsigned; NULL; DEFAULT 0 — Define si se toma en cuenta para el conteo de inventario
 * - `establishment_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `contract_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `devolution_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `dispatch_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `document_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `expense_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `fixed_asset_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `fixed_asset_purchase_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `order_form_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `order_note_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `purchase_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `purchase_order_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `purchase_quotation_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `quotation_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `sale_note_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `sale_opportunity_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
 * - `technical_service_item_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con la tabla
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
CREATE TABLE `item_movement` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con item',
  `quantity` decimal(12,4) DEFAULT '0.0000' COMMENT 'Cantidad de venta del item',
  `date_of_movement` timestamp NULL DEFAULT NULL,
  `countable` tinyint(3) unsigned DEFAULT '0' COMMENT 'Define si se toma en cuenta para el conteo de inventario',
  `establishment_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `contract_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `devolution_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `dispatch_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `document_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `expense_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `fixed_asset_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `fixed_asset_purchase_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `order_form_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `order_note_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `purchase_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `purchase_order_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `purchase_quotation_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `quotation_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `sale_note_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `sale_opportunity_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `technical_service_item_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con la tabla',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_movement_item_id_index` (`item_id`),
  KEY `item_movement_quantity_index` (`quantity`),
  KEY `item_movement_date_of_movement_index` (`date_of_movement`),
  KEY `item_movement_countable_index` (`countable`),
  KEY `item_movement_establishment_id_index` (`establishment_id`),
  KEY `item_movement_contract_item_id_index` (`contract_item_id`),
  KEY `item_movement_devolution_item_id_index` (`devolution_item_id`),
  KEY `item_movement_dispatch_item_id_index` (`dispatch_item_id`),
  KEY `item_movement_document_item_id_index` (`document_item_id`),
  KEY `item_movement_expense_item_id_index` (`expense_item_id`),
  KEY `item_movement_fixed_asset_item_id_index` (`fixed_asset_item_id`),
  KEY `item_movement_fixed_asset_purchase_item_id_index` (`fixed_asset_purchase_item_id`),
  KEY `item_movement_order_form_item_id_index` (`order_form_item_id`),
  KEY `item_movement_order_note_item_id_index` (`order_note_item_id`),
  KEY `item_movement_purchase_item_id_index` (`purchase_item_id`),
  KEY `item_movement_purchase_order_item_id_index` (`purchase_order_item_id`),
  KEY `item_movement_purchase_quotation_item_id_index` (`purchase_quotation_item_id`),
  KEY `item_movement_quotation_item_id_index` (`quotation_item_id`),
  KEY `item_movement_sale_note_item_id_index` (`sale_note_item_id`),
  KEY `item_movement_sale_opportunity_item_id_index` (`sale_opportunity_item_id`),
  KEY `item_movement_technical_service_item_id_index` (`technical_service_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_movement`');
    }
};
// ######### FIN CAMBIO NELSON #########

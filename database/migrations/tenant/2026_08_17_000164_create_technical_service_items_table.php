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
 * Tabla: `technical_service_items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `technical_services_id`: int(10) unsigned; NULL; DEFAULT 0 — Id de technical_services
 * - `item_id`: int(10) unsigned; NULL; DEFAULT 0 — Id de item
 * - `item`: json; NULL — Json con el contenido de item
 * - `quantity`: double(12,4); NULL; DEFAULT 0.0000 — Cantidad de item usado
 * - `unit_value`: double(16,6); NULL; DEFAULT 0.000000 — unit_value
 * - `affectation_igv_type_id`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Tipo de afectacion dde igv. cat_affectation_igv_types
 * - `total_base_igv`: double(12,2); NULL; DEFAULT 0.00 — Monto base del IGV
 * - `percentage_igv`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `system_isc_type_id`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_base_isc`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_isc`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_isc`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_other_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `percentage_other_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_other_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_plastic_bag_taxes`: double(6,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `price_type_id`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unit_price`: double(16,6); NULL; DEFAULT 0.000000 — Sin comentario definido en el esquema fuente.
 * - `total_value`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_charge`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_discount`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `attributes`: json; NULL — Atributos
 * - `discounts`: json; NULL — Descuentos
 * - `charges`: json; NULL — Cargos
 * - `additional_information`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Informacion adicional
 * - `warehouse_id`: int(10) unsigned; NULL; DEFAULT 0 — Id de item
 * - `name_product_pdf`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Nombre de producto en el pdf
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
CREATE TABLE `technical_service_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `technical_services_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de technical_services',
  `item_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de item',
  `item` json DEFAULT NULL COMMENT 'Json con el contenido de item',
  `quantity` double(12,4) DEFAULT '0.0000' COMMENT 'Cantidad de item usado',
  `unit_value` double(16,6) DEFAULT '0.000000' COMMENT 'unit_value',
  `affectation_igv_type_id` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Tipo de afectacion dde igv. cat_affectation_igv_types',
  `total_base_igv` double(12,2) DEFAULT '0.00' COMMENT 'Monto base del IGV',
  `percentage_igv` double(12,2) DEFAULT '0.00',
  `total_igv` double(12,2) DEFAULT '0.00',
  `system_isc_type_id` longtext COLLATE utf8mb4_unicode_ci,
  `total_base_isc` double(12,2) DEFAULT '0.00',
  `percentage_isc` double(12,2) DEFAULT '0.00',
  `total_isc` double(12,2) DEFAULT '0.00',
  `total_base_other_taxes` double(12,2) DEFAULT '0.00',
  `percentage_other_taxes` double(12,2) DEFAULT '0.00',
  `total_other_taxes` double(12,2) DEFAULT '0.00',
  `total_plastic_bag_taxes` double(6,2) DEFAULT '0.00',
  `total_taxes` double(12,2) DEFAULT '0.00',
  `price_type_id` longtext COLLATE utf8mb4_unicode_ci,
  `unit_price` double(16,6) DEFAULT '0.000000',
  `total_value` double(12,2) DEFAULT '0.00',
  `total_charge` double(12,2) DEFAULT '0.00',
  `total_discount` double(12,2) DEFAULT '0.00',
  `total` double(12,2) DEFAULT '0.00',
  `attributes` json DEFAULT NULL COMMENT 'Atributos',
  `discounts` json DEFAULT NULL COMMENT 'Descuentos',
  `charges` json DEFAULT NULL COMMENT 'Cargos',
  `additional_information` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Informacion adicional',
  `warehouse_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de item',
  `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de producto en el pdf',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `technical_service_items`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `technical_service_items` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `technical_services_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de technical_services'
 * - `item_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de item'
 * - `item` json DEFAULT NULL COMMENT 'Json con el contenido de item'
 * - `quantity` double(12,4) DEFAULT '0.0000' COMMENT 'Cantidad de item usado'
 * - `unit_value` double(16,6) DEFAULT '0.000000' COMMENT 'unit_value'
 * - `affectation_igv_type_id` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Tipo de afectacion dde igv. cat_affectation_igv_types'
 * - `total_base_igv` double(12,2) DEFAULT '0.00' COMMENT 'Monto base del IGV'
 * - `percentage_igv` double(12,2) DEFAULT '0.00'
 * - `total_igv` double(12,2) DEFAULT '0.00'
 * - `total_base_other_taxes` double(12,2) DEFAULT '0.00'
 * - `percentage_other_taxes` double(12,2) DEFAULT '0.00'
 * - `total_other_taxes` double(12,2) DEFAULT '0.00'
 * - `total_taxes` double(12,2) DEFAULT '0.00'
 * - `price_type_id` longtext COLLATE utf8mb4_unicode_ci
 * - `unit_price` double(16,6) DEFAULT '0.000000'
 * - `total_value` double(12,2) DEFAULT '0.00'
 * - `total_charge` double(12,2) DEFAULT '0.00'
 * - `total_discount` double(12,2) DEFAULT '0.00'
 * - `total` double(12,2) DEFAULT '0.00'
 * - `attributes` json DEFAULT NULL COMMENT 'Atributos'
 * - `discounts` json DEFAULT NULL COMMENT 'Descuentos'
 * - `charges` json DEFAULT NULL COMMENT 'Cargos'
 * - `additional_information` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Informacion adicional'
 * - `warehouse_id` int(10) unsigned DEFAULT '0' COMMENT 'Id de item'
 * - `name_product_pdf` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de producto en el pdf'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
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
  `total_base_other_taxes` double(12,2) DEFAULT '0.00',
  `percentage_other_taxes` double(12,2) DEFAULT '0.00',
  `total_other_taxes` double(12,2) DEFAULT '0.00',
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
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `purchase_settlement_items`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `purchase_settlement_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `item`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `unit_value`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `affectation_igv_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `total_base_igv`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `percentage_igv`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_igv`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `price_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `unit_price`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total_value`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `income_tax_affectation_igv_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `income_retention_percentage`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `income_retention_amount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `purchase_settlement_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_settlement_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item` json NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_value` decimal(12,2) NOT NULL,
  `affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_base_igv` decimal(12,2) NOT NULL,
  `percentage_igv` decimal(12,2) NOT NULL,
  `total_igv` decimal(12,2) NOT NULL,
  `total_taxes` decimal(12,2) NOT NULL,
  `price_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_value` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `income_tax_affectation_igv_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `income_retention_percentage` decimal(12,2) NOT NULL DEFAULT '0.00',
  `income_retention_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `purchase_settlement_items_purchase_settlement_id_foreign` (`purchase_settlement_id`),
  KEY `purchase_settlement_items_item_id_foreign` (`item_id`),
  KEY `p_s_i_income_tax_affectation_igv_type_id_fk` (`income_tax_affectation_igv_type_id`),
  KEY `purchase_settlement_items_affectation_igv_type_id_foreign` (`affectation_igv_type_id`),
  KEY `purchase_settlement_items_price_type_id_foreign` (`price_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `purchase_settlement_items`');
    }
};
// ######### FIN CAMBIO NELSON #########

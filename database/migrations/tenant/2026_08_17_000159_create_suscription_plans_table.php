<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `suscription_plans`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `cat_period_id`: int(10) unsigned; NULL; DEFAULT 0 — Relacion con el periodo de tiempo
 * - `name`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Nombre del plan
 * - `description`: longtext; NOT NULL; COLLATE utf8mb4_unicode_ci — Descripcion del plan
 * - `total`: double(12,2); NULL; DEFAULT 0.00 — El total del costo del plan
 * - `currency_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `quantity_period`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `unlimited`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `trial_days`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `status`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `exchange_rate_sale`: double(13,3); NULL; DEFAULT 0.000 — Sin comentario definido en el esquema fuente.
 * - `total_prepayment`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_charge`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_discount`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_exportation`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_free`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxed`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_unaffected`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_exonerated`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_igv_free`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_isc`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_isc`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_base_other_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_other_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_taxes`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `total_value`: double(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `charges`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `attributes`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `discounts`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `prepayments`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `related`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `perception`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `detraction`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `legends`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `terms_condition`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `suscription_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cat_period_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con el periodo de tiempo',
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del plan',
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripcion del plan',
  `total` double(12,2) DEFAULT '0.00' COMMENT 'El total del costo del plan',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_period` int(10) unsigned DEFAULT NULL,
  `unlimited` tinyint(1) NOT NULL DEFAULT '0',
  `trial_days` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `exchange_rate_sale` double(13,3) DEFAULT '0.000',
  `total_prepayment` double(12,2) DEFAULT '0.00',
  `total_charge` double(12,2) DEFAULT '0.00',
  `total_discount` double(12,2) DEFAULT '0.00',
  `total_exportation` double(12,2) DEFAULT '0.00',
  `total_free` double(12,2) DEFAULT '0.00',
  `total_taxed` double(12,2) DEFAULT '0.00',
  `total_unaffected` double(12,2) DEFAULT '0.00',
  `total_exonerated` double(12,2) DEFAULT '0.00',
  `total_igv` double(12,2) DEFAULT '0.00',
  `total_igv_free` double(12,2) DEFAULT '0.00',
  `total_base_isc` double(12,2) DEFAULT '0.00',
  `total_isc` double(12,2) DEFAULT '0.00',
  `total_base_other_taxes` double(12,2) DEFAULT '0.00',
  `total_other_taxes` double(12,2) DEFAULT '0.00',
  `total_taxes` double(12,2) DEFAULT '0.00',
  `total_value` double(12,2) DEFAULT '0.00',
  `charges` json DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `discounts` json DEFAULT NULL,
  `prepayments` json DEFAULT NULL,
  `related` json DEFAULT NULL,
  `perception` json DEFAULT NULL,
  `detraction` json DEFAULT NULL,
  `legends` json DEFAULT NULL,
  `terms_condition` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `suscription_plans`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `purchase_supplies`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `purchase_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `supply_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `cost`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `affectation_igv_type_id`: char(2); NOT NULL; DEFAULT 10; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `purchase_supplies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `supply_id` int(10) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `cost` decimal(12,2) NOT NULL,
  `affectation_igv_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_supplies_purchase_id_foreign` (`purchase_id`),
  KEY `purchase_supplies_supply_id_foreign` (`supply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `purchase_supplies`');
    }
};

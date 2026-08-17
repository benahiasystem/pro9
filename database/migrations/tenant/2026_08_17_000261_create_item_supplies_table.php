<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `item_supplies`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `individual_item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(13,3); NULL; DEFAULT 0.000 — Sin comentario definido en el esquema fuente.
 * - `unit_price`: decimal(12,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `item_supplies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `individual_item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(13,3) DEFAULT '0.000',
  `unit_price` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_supplies_item_id_foreign` (`item_id`),
  KEY `item_supplies_individual_item_id_foreign` (`individual_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `item_supplies`');
    }
};

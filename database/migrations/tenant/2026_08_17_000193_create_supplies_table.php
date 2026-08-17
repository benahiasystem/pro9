<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `supplies`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `cost`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `unit_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `waste_percentage`: decimal(5,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `stock`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
 * - `minimum_stock`: decimal(12,4); NOT NULL; DEFAULT 0.0000 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `supplies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waste_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `stock` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `minimum_stock` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplies_unit_type_id_foreign` (`unit_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `supplies`');
    }
};

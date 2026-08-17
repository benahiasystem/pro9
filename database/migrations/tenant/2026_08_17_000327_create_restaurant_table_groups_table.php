<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `restaurant_table_groups`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `main_table_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `status`: varchar(255); NOT NULL; DEFAULT open; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `opening_date`: datetime; NULL — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(10,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `restaurant_table_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `main_table_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `opening_date` datetime DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurant_table_groups_main_table_id_foreign` (`main_table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_table_groups`');
    }
};

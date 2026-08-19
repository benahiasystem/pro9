<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `dispatch_addresses`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `person_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `address`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `location_id`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `establishment_code`: varchar(4); NOT NULL; DEFAULT 0000; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `is_active`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `dispatch_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` json NOT NULL,
  `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispatch_address_unique` (`person_id`,`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `dispatch_addresses`');
    }
};
// ######### FIN CAMBIO NELSON #########

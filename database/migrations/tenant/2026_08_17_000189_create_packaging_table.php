<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `packaging`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_extra_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(8,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `number_packages`: decimal(8,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `item`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `observation`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `lot_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_start`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `time_start`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `date_end`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `time_end`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `packaging_collaborator`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `packaging` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_extra_data` json DEFAULT NULL,
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(8,2) DEFAULT '0.00',
  `number_packages` decimal(8,2) DEFAULT '0.00',
  `item` json DEFAULT NULL,
  `observation` longtext COLLATE utf8mb4_unicode_ci,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `packaging_collaborator` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `packaging_soap_type_id_foreign` (`soap_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `packaging`');
    }
};

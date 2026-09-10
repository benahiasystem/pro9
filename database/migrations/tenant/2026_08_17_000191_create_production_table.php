<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `production`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `fiscal_environment`: varchar(16); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `inventory_id_reference`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NULL; DEFAULT 0.0000 — Peso dle insumo
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `machine_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `production_order`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `comment`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_start`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `time_start`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `date_end`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `time_end`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `lot_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_extra_data`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `mix_date_start`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `mix_time_start`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `mix_date_end`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `mix_time_end`: time; NULL — Sin comentario definido en el esquema fuente.
 * - `informative`: tinyint(3) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `agreed`: decimal(8,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `imperfect`: decimal(8,2); NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `proccess_type`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `production_collaborator`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `mix_collaborator`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `production` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT '0',
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int(10) unsigned DEFAULT '0',
  `inventory_id_reference` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(12,4) DEFAULT '0.0000' COMMENT 'Peso dle insumo ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `machine_id` int(10) unsigned NOT NULL DEFAULT '0',
  `production_order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `date_start` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_extra_data` json DEFAULT NULL,
  `mix_date_start` date DEFAULT NULL,
  `mix_time_start` time DEFAULT NULL,
  `mix_date_end` date DEFAULT NULL,
  `mix_time_end` time DEFAULT NULL,
  `informative` tinyint(3) unsigned DEFAULT '0',
  `agreed` decimal(8,2) DEFAULT '0.00',
  `imperfect` decimal(8,2) DEFAULT '0.00',
  `proccess_type` longtext COLLATE utf8mb4_unicode_ci,
  `production_collaborator` text COLLATE utf8mb4_unicode_ci,
  `mix_collaborator` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `production_fiscal_environment_foreign` (`fiscal_environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `production`');
    }
};
// ######### FIN CAMBIO NELSON #########

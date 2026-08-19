<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `weighted_average_costs`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `origin_id`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `origin_type`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `item_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `cost`: decimal(16,6); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `weighted_cost`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `stock`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `weighted_average_costs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_of_issue` date NOT NULL,
  `origin_id` int(11) NOT NULL,
  `origin_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `cost` decimal(16,6) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `weighted_cost` decimal(12,2) NOT NULL,
  `stock` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `origin_index` (`origin_id`,`origin_type`),
  KEY `weighted_average_costs_item_id_foreign` (`item_id`),
  KEY `weighted_average_costs_date_of_issue_index` (`date_of_issue`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `weighted_average_costs`');
    }
};
// ######### FIN CAMBIO NELSON #########

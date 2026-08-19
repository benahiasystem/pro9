<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `restaurant_tables`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `status`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `products`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `total`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `personas`: int(11); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `cliente`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `comentarios`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `label`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `shape`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `environment`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `original_environment`: varchar(100); NULL; COLLATE utf8mb4_unicode_ci — Ambiente original de la mesa (NULL si no ha sido movida)
 * - `waiter`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `opening_date`: datetime; NULL — Sin comentario definido en el esquema fuente.
 * - `order_status`: varchar(255); NOT NULL; DEFAULT pending; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `group_id`: bigint(20) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `is_active`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `is_paid`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `delivery`: json; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `restaurant_tables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `products` json DEFAULT NULL,
  `total` decimal(12,2) NOT NULL,
  `personas` int(11) NOT NULL,
  `cliente` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentarios` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shape` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `environment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_environment` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ambiente original de la mesa (NULL si no ha sido movida)',
  `waiter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_date` datetime DEFAULT NULL,
  `order_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `delivery` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurant_tables_group_id_foreign` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_tables`');
    }
};
// ######### FIN CAMBIO NELSON #########

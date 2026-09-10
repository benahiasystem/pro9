<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `restaurant_tables` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `products` json DEFAULT NULL
 * - `total` decimal(12,2) NOT NULL
 * - `personas` int(11) NOT NULL
 * - `cliente` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `comentarios` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `shape` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `environment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `original_environment` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ambiente original de la mesa (NULL si no ha sido movida)'
 * - `waiter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `opening_date` datetime DEFAULT NULL
 * - `order_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
 * - `group_id` bigint(20) unsigned DEFAULT NULL
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 * - `is_paid` tinyint(1) NOT NULL DEFAULT '0'
 * - `delivery` json DEFAULT NULL
 */
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
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

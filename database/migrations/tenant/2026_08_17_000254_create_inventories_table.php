<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `inventories` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `type` enum('1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `warehouse_id` int(10) unsigned NOT NULL
 * - `warehouse_destination_id` int(10) unsigned DEFAULT NULL
 * - `inventory_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `quantity` decimal(12,4) NOT NULL
 * - `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `inventories_transfer_id` int(10) unsigned DEFAULT NULL
 * - `comments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_of_issue` date DEFAULT NULL
 * - `guide_id` int(10) unsigned DEFAULT NULL
 * - `system_stock` decimal(12,4) DEFAULT NULL
 * - `real_stock` decimal(12,4) DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `warehouse_destination_id` int(10) unsigned DEFAULT NULL,
  `inventory_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `lot_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventories_transfer_id` int(10) unsigned DEFAULT NULL,
  `comments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_issue` date DEFAULT NULL,
  `guide_id` int(10) unsigned DEFAULT NULL,
  `system_stock` decimal(12,4) DEFAULT NULL,
  `real_stock` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventories_item_id_foreign` (`item_id`),
  KEY `inventories_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventories_inventory_transaction_id_foreign` (`inventory_transaction_id`),
  KEY `inventories_inventories_transfer_id_foreign` (`inventories_transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `inventories`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

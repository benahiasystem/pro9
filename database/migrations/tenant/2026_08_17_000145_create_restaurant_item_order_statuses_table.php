<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `restaurant_item_order_statuses` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `table_id` int(11) NOT NULL
 * - `item_id` int(11) NOT NULL
 * - `item` json DEFAULT NULL
 * - `quantity` int(11) NOT NULL
 * - `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `status` int(11) NOT NULL
 * - `status_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `restaurant_item_order_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item` json DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL,
  `status_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_item_order_statuses`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

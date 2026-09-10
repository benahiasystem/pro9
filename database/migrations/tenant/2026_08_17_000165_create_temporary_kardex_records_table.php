<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `temporary_kardex_records` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `inventory_kardex_id` int(11) DEFAULT NULL
 * - `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_time` datetime DEFAULT NULL
 * - `date_of_issue` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `sale_note_asoc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `order_note_asoc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `doc_asoc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `inventory_kardexable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_warehouse_price` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `warehouse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `input` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `output` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `balance` decimal(8,2) DEFAULT NULL
 * - `type_transaction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date_of_register` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `guide_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `transfer_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `temporary_kardex_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_kardex_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `date_of_issue` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_note_asoc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_note_asoc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_asoc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_kardexable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_warehouse_price` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance` decimal(8,2) DEFAULT NULL,
  `type_transaction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_register` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guide_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `temporary_kardex_records`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

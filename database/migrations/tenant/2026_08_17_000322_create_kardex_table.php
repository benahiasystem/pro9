<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `kardex` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `date_of_issue` date NOT NULL
 * - `type` enum('sale','purchase') COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `item_id` int(10) unsigned NOT NULL
 * - `document_id` int(10) unsigned DEFAULT NULL
 * - `purchase_id` int(10) unsigned DEFAULT NULL
 * - `purchase_settlement_id` int(10) unsigned DEFAULT NULL
 * - `sale_note_id` int(10) unsigned DEFAULT NULL
 * - `quantity` decimal(12,4) NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `kardex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_of_issue` date NOT NULL,
  `type` enum('sale','purchase') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned DEFAULT NULL,
  `purchase_id` int(10) unsigned DEFAULT NULL,
  `purchase_settlement_id` int(10) unsigned DEFAULT NULL,
  `sale_note_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kardex_purchase_id_foreign` (`purchase_id`),
  KEY `kardex_document_id_foreign` (`document_id`),
  KEY `kardex_item_id_foreign` (`item_id`),
  KEY `kardex_sale_note_id_foreign` (`sale_note_id`),
  KEY `kardex_purchase_settlement_id_foreign` (`purchase_settlement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `kardex`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

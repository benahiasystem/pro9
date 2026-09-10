<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `inventories_transfer` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `external_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `document_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `series` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `number` int(11) DEFAULT NULL
 * - `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `warehouse_id` int(10) unsigned DEFAULT NULL
 * - `warehouse_destination_id` int(10) unsigned DEFAULT NULL
 * - `transfer_collect_id` int(10) unsigned DEFAULT NULL
 * - `quantity` decimal(12,4) NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `user_id` int(10) unsigned DEFAULT '0' COMMENT 'usuario que crea el registro'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventories_transfer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` int(11) DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `warehouse_destination_id` int(10) unsigned DEFAULT NULL,
  `transfer_collect_id` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT '0' COMMENT 'usuario que crea el registro',
  PRIMARY KEY (`id`),
  KEY `inventories_transfer_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventories_transfer_warehouse_destination_id_foreign` (`warehouse_destination_id`),
  KEY `inventories_transfer_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `inventories_transfer_document_type_id_foreign` (`document_type_id`),
  KEY `inventories_transfer_transfer_collect_id_foreign` (`transfer_collect_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `inventories_transfer`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

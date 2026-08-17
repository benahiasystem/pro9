<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `inventories_transfer`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `external_id`: char(36); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `document_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `series`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: int(11); NULL — Sin comentario definido en el esquema fuente.
 * - `filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `warehouse_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `warehouse_destination_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `transfer_collect_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `quantity`: decimal(12,4); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NULL; DEFAULT 0 — usuario que crea el registro
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `inventories_transfer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `inventories_transfer_soap_type_id_foreign` (`soap_type_id`),
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `guide_files` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `filename` text COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de archivo'
 * - `purchase_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con purchases'
 * - `document_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con documents'
 * - `order_note_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con order_notes'
 * - `quotation_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con quotations'
 * - `sale_note_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con sale_notes'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `guide_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` text COLLATE utf8mb4_unicode_ci COMMENT 'Nombre de archivo',
  `purchase_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con purchases',
  `document_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con documents',
  `order_note_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con order_notes',
  `quotation_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con quotations',
  `sale_note_id` int(10) unsigned DEFAULT '0' COMMENT 'Relacion con sale_notes',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `guide_files`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

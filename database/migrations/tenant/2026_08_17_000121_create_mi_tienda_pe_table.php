<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `mi_tienda_pe` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `order_number` text COLLATE utf8mb4_unicode_ci COMMENT 'Numero de pedido'
 * - `transaction_code` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de pasareña'
 * - `order_note_id` int(10) unsigned DEFAULT '0'
 * - `document_id` int(10) unsigned DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `mi_tienda_pe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` text COLLATE utf8mb4_unicode_ci COMMENT 'Numero de pedido',
  `transaction_code` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de pasareña',
  `order_note_id` int(10) unsigned DEFAULT '0',
  `document_id` int(10) unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `mi_tienda_pe`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

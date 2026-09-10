<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `cat_transfer_reason_types` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `discount_stock` tinyint(1) DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_transfer_reason_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_stock` tinyint(1) DEFAULT '0',
  KEY `cat_transfer_reason_types_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_transfer_reason_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

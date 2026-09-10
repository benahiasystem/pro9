<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `company_accounts` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `subtotal_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `igv_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `subtotal_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `total_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `igv_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `exonerated` int(11) DEFAULT NULL
 * - `unaffected` int(11) DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `company_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subtotal_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `igv_pen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `igv_usd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exonerated` int(11) DEFAULT NULL,
  `unaffected` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `company_accounts`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

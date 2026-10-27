<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
/** Familias de impuestos de referencia para instalaciones nuevas.
 * - `id` varchar(16) NOT NULL, código de familia
 * - `description` varchar(255) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_taxes_types` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_taxes_types`');
    }
};
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Conceptos de retención ISLR del manual SENIAT 3.1 (2014), para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` char(3) COLLATE utf8mb4_unicode_ci NOT NULL, código del concepto
 * - `description` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL, actividad
 * - `percentage_label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL, porcentaje textual histórico
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_retention_concept` (
  `id` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage_label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_retention_concept`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Tipos de alícuota del catálogo HKA, para instalaciones nuevas.
 * Los porcentajes reproducen el manual y no configuran el cálculo fiscal de Pro9.
 * Inventario de columnas:
 * - `id` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL, código HKA
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `percentage` decimal(5,2) NOT NULL, valor de referencia del manual
 * - `tax_kind` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL, IVA o IGTF
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_iva_rate_types` (
  `id` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `tax_kind` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_iva_rate_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

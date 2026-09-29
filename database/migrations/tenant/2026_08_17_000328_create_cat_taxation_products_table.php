<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial del catálogo HKA `cat_taxation_products` para instalaciones nuevas.
 * Los identificadores son locales porque HKA no publica códigos para estos valores.
 * Inventario de columnas:
 * - `id` tinyint unsigned NOT NULL, clave primaria local
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `active` tinyint(1) NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `cat_taxation_products` (
  `id` tinyint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `cat_taxation_products`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `invoices` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `operation_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `date_of_due` date NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `operation_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_due` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoices_document_id_foreign` (`document_id`),
  KEY `invoices_operation_type_id_foreign` (`operation_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `invoices`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

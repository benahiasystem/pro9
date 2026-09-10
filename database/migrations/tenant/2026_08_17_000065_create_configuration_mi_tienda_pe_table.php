<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `configuration_mi_tienda_pe` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `establishment_id` int(10) unsigned DEFAULT '1'
 * - `series_order_note_id` int(10) unsigned DEFAULT '0'
 * - `series_document_ft_id` int(10) unsigned DEFAULT '0'
 * - `user_id` int(10) unsigned DEFAULT '0'
 * - `payment_destination_id` int(10) unsigned DEFAULT '0'
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `autogenerate` tinyint(3) unsigned DEFAULT '0'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `configuration_mi_tienda_pe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `establishment_id` int(10) unsigned DEFAULT '1',
  `series_order_note_id` int(10) unsigned DEFAULT '0',
  `series_document_ft_id` int(10) unsigned DEFAULT '0',
  `user_id` int(10) unsigned DEFAULT '0',
  `payment_destination_id` int(10) unsigned DEFAULT '0',
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `autogenerate` tinyint(3) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `configuration_mi_tienda_pe`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

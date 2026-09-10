<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `campaign_category` para instalaciones nuevas.
 * Inventario de columnas:
 * - `discount_campaign_id` bigint(20) unsigned NOT NULL
 * - `category_id` int(10) unsigned NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `campaign_category` (
  `discount_campaign_id` bigint(20) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`discount_campaign_id`,`category_id`),
  KEY `campaign_category_category_id_foreign` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `campaign_category`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

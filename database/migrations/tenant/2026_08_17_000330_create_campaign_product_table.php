<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `campaign_product` para instalaciones nuevas.
 * Inventario de columnas:
 * - `discount_campaign_id` bigint(20) unsigned NOT NULL
 * - `item_id` int(10) unsigned NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `campaign_product` (
  `discount_campaign_id` bigint(20) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`discount_campaign_id`,`item_id`),
  KEY `campaign_product_item_id_foreign` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `campaign_product`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `payment_link_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `payment_link_id` int(10) unsigned NOT NULL
 * - `record_id` int(10) unsigned DEFAULT NULL
 * - `record_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `payment_id` int(10) unsigned DEFAULT NULL
 * - `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `total` decimal(12,2) DEFAULT NULL
 * - `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_link_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_link_id` int(10) unsigned NOT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `record_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_link_payments_record_index` (`record_id`,`record_type`),
  KEY `payment_link_payments_payment_index` (`payment_id`,`payment_type`),
  KEY `payment_link_payments_status_index` (`payment_link_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_link_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

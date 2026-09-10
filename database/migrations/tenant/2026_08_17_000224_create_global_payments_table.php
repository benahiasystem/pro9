<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `global_payments` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `destination_id` int(11) DEFAULT NULL
 * - `destination_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_id` int(11) NOT NULL
 * - `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `user_id` int(10) unsigned DEFAULT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `global_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_id` int(11) DEFAULT NULL,
  `destination_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` int(11) NOT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `destination_index` (`destination_id`,`destination_type`),
  KEY `payment_index` (`payment_id`,`payment_type`),
  KEY `global_payments_fiscal_environment_foreign` (`fiscal_environment`),
  KEY `global_payments_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `global_payments`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

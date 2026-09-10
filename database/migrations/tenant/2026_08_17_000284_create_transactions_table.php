<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `transactions` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `payment_link_id` int(10) unsigned NOT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `date` date NOT NULL
 * - `time` time NOT NULL
 * - `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `payment_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `amount` decimal(16,2) NOT NULL
 * - `transaction_state_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_link_id` int(10) unsigned NOT NULL,
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `transaction_state_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactions_uuid_unique` (`uuid`),
  KEY `transactions_payment_link_id_foreign` (`payment_link_id`),
  KEY `transactions_transaction_state_id_foreign` (`transaction_state_id`),
  KEY `transactions_date_index` (`date`),
  KEY `transactions_time_index` (`time`),
  KEY `transactions_description_index` (`description`),
  KEY `transactions_payment_id_index` (`payment_id`),
  KEY `transactions_amount_index` (`amount`),
  KEY `transactions_fiscal_environment_foreign` (`fiscal_environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `transactions`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO DESEMBOLSOS DE VUELTO ########
/** Instalaciones nuevas: una salida individual por cobro y posición, con fracciones exactas. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_change_disbursements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `document_payment_id` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_amount` decimal(30,12) NOT NULL,
  `exchange_rate` decimal(18,8) NOT NULL,
  `date_of_payment` date NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculation_snapshot` json NOT NULL,
  `context` json NOT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_change_payment_position_unique` (`document_payment_id`,`position`),
  KEY `document_change_document_index` (`document_id`),
  KEY `document_change_method_index` (`payment_method_type_id`),
  KEY `document_change_currency_index` (`currency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `document_change_disbursements`'); }
};
// ######## FIN DESEMBOLSOS DE VUELTO ########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `document_fee` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `document_id` int(10) unsigned NOT NULL
 * - `date` date NOT NULL
 * - `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `amount` decimal(12,2) NOT NULL
 * - `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relacion con el metodo de pago, Nulo es pago a cuotas'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `document_fee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relacion con el metodo de pago, Nulo es pago a cuotas',
  PRIMARY KEY (`id`),
  KEY `document_fee_document_id_foreign` (`document_id`),
  KEY `document_fee_currency_type_id_foreign` (`currency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `document_fee`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

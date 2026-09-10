<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `payment_configurations` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `enabled_yape` tinyint(1) NOT NULL
 * - `qrcode_yape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `name_yape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `telephone_yape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `enabled_mp` tinyint(1) NOT NULL DEFAULT '0'
 * - `access_token_mp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `public_key_mp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `publickey_culqi` text COLLATE utf8mb4_unicode_ci
 * - `privatekey_culqi` text COLLATE utf8mb4_unicode_ci
 * - `enabled_culqi` tinyint(1) NOT NULL DEFAULT '0'
 * - `enabled_izipay` tinyint(1) NOT NULL DEFAULT '0'
 * - `default_payment_for_payment_links` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `idrsa_culqi` text COLLATE utf8mb4_unicode_ci
 * - `rsa_culqi` text COLLATE utf8mb4_unicode_ci
 * - `username_izipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `password_izipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `publickey_izipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `sha256key_izipay` text COLLATE utf8mb4_unicode_ci
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `enabled_yape` tinyint(1) NOT NULL,
  `qrcode_yape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_yape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_yape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled_mp` tinyint(1) NOT NULL DEFAULT '0',
  `access_token_mp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_key_mp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publickey_culqi` text COLLATE utf8mb4_unicode_ci,
  `privatekey_culqi` text COLLATE utf8mb4_unicode_ci,
  `enabled_culqi` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_izipay` tinyint(1) NOT NULL DEFAULT '0',
  `default_payment_for_payment_links` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idrsa_culqi` text COLLATE utf8mb4_unicode_ci,
  `rsa_culqi` text COLLATE utf8mb4_unicode_ci,
  `username_izipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_izipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publickey_izipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sha256key_izipay` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_configurations`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

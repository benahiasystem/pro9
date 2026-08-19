<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `payment_configurations`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `enabled_yape`: tinyint(1); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `qrcode_yape`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name_yape`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `telephone_yape`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `enabled_mp`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `access_token_mp`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `public_key_mp`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `publickey_culqi`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `privatekey_culqi`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `enabled_culqi`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_izipay`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `default_payment_for_payment_links`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `idrsa_culqi`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `rsa_culqi`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `username_izipay`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `password_izipay`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `publickey_izipay`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sha256key_izipay`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_configurations`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
         * Run the migrations.
         *
         * @return void
         */

/**
         * Reverse the migrations.
         *
         * @return void
         */

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `transfer_account_payments`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `origin_id`: int(10) unsigned; NULL; DEFAULT 0 — id Cuenta de origen
 * - `origin_type`: text; NULL; COLLATE utf8mb4_unicode_ci — Modelo de origen
 * - `destiny_id`: int(10) unsigned; NULL; DEFAULT 0 — id Cuenta de destino
 * - `destiny_type`: text; NULL; COLLATE utf8mb4_unicode_ci — Modelo de destino
 * - `amount`: decimal(9,2); NULL; DEFAULT 0.00 — Monto a transferir
 * - `date_of_movement`: datetime; NULL — Fecha de movimiento
 * - `user_id`: int(10) unsigned; NULL; DEFAULT 0 — Usuario que realiza el movimiento
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
CREATE TABLE `transfer_account_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `origin_id` int(10) unsigned DEFAULT '0' COMMENT 'id Cuenta de origen',
  `origin_type` text COLLATE utf8mb4_unicode_ci COMMENT 'Modelo de origen',
  `destiny_id` int(10) unsigned DEFAULT '0' COMMENT 'id Cuenta de destino',
  `destiny_type` text COLLATE utf8mb4_unicode_ci COMMENT 'Modelo de destino',
  `amount` decimal(9,2) DEFAULT '0.00' COMMENT 'Monto a transferir',
  `date_of_movement` datetime DEFAULT NULL COMMENT 'Fecha de movimiento',
  `user_id` int(10) unsigned DEFAULT '0' COMMENT 'Usuario que realiza el movimiento',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `transfer_account_payments`');
    }
};
// ######### FIN CAMBIO NELSON #########

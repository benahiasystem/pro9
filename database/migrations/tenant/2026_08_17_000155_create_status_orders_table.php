<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `status_orders`.
 *
 * Inventario de columnas:
 * - `id`: tinyint(3) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `description`: varchar(30); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `color`: varchar(7); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sort_order`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `is_initial`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `is_final`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `is_payment_status`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `is_order_status`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `is_shipping_status`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_generate_document`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_discount_stock`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_mark_payment`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_send_email`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_notify_dispatch`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_generate_remission`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_free_reserved_stock`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_block_returns`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `action_void_order`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `status_orders` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `is_final` tinyint(1) NOT NULL DEFAULT '0',
  `is_payment_status` tinyint(1) NOT NULL DEFAULT '0',
  `is_order_status` tinyint(1) NOT NULL DEFAULT '1',
  `is_shipping_status` tinyint(1) NOT NULL DEFAULT '0',
  `action_generate_document` tinyint(1) NOT NULL DEFAULT '0',
  `action_discount_stock` tinyint(1) NOT NULL DEFAULT '0',
  `action_mark_payment` tinyint(1) NOT NULL DEFAULT '0',
  `action_send_email` tinyint(1) NOT NULL DEFAULT '0',
  `action_notify_dispatch` tinyint(1) NOT NULL DEFAULT '0',
  `action_generate_remission` tinyint(1) NOT NULL DEFAULT '0',
  `action_free_reserved_stock` tinyint(1) NOT NULL DEFAULT '0',
  `action_block_returns` tinyint(1) NOT NULL DEFAULT '0',
  `action_void_order` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `status_orders`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `suscription_orders`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `external_id`: varchar(6); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: varchar(20); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `suscription_id`: bigint(20) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `type`: enum('new_suscription','suscription_order'); NOT NULL; DEFAULT suscription_order; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `person_number`: varchar(25); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `count_rejected_payments`: int(11); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `amount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `date_of_issue`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_due`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_payment`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `status`: enum('pending','paid','canceled','rejected','expired'); NOT NULL; DEFAULT pending; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `documentable_type`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `documentable_id`: bigint(20) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `count_notification`: int(11); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `date_notification`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `suscription_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suscription_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('new_suscription','suscription_order') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'suscription_order',
  `person_number` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count_rejected_payments` int(11) NOT NULL DEFAULT '0',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `date_of_issue` timestamp NULL DEFAULT NULL,
  `date_of_due` timestamp NULL DEFAULT NULL,
  `date_of_payment` timestamp NULL DEFAULT NULL,
  `status` enum('pending','paid','canceled','rejected','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `documentable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentable_id` bigint(20) unsigned DEFAULT NULL,
  `count_notification` int(11) NOT NULL DEFAULT '0',
  `date_notification` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suscription_orders_external_id_unique` (`external_id`),
  KEY `suscription_orders_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `suscription_orders`');
    }
};
// ######### FIN CAMBIO NELSON #########

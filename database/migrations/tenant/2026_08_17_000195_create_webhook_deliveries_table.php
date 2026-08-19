<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `webhook_deliveries`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `webhook_subscription_id`: bigint(20) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `event`: varchar(50); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `event_id`: char(36); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `payload`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `status`: varchar(10); NOT NULL; DEFAULT pending; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `attempts`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `response_code`: smallint(6); NULL — Sin comentario definido en el esquema fuente.
 * - `response_body`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `error_message`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sent_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `webhook_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_subscription_id` bigint(20) unsigned NOT NULL,
  `event` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `response_code` smallint(6) DEFAULT NULL,
  `response_body` text COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhook_deliveries_webhook_subscription_id_status_index` (`webhook_subscription_id`,`status`),
  KEY `webhook_deliveries_created_at_index` (`created_at`),
  KEY `webhook_deliveries_event_index` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `webhook_deliveries`');
    }
};
// ######### FIN CAMBIO NELSON #########

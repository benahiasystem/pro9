<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `received_payments`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `provider`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `amount`: decimal(10,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `sender_name`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `raw_text`: text; NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `package_name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `posted_at`: datetime; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `device_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `dedup_hash`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `status`: varchar(255); NOT NULL; DEFAULT pending; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `matched_document_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `matched_document_payment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `claimed_by_user_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `received_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `sender_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `posted_at` datetime NOT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dedup_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `matched_document_id` int(10) unsigned DEFAULT NULL,
  `matched_document_payment_id` int(10) unsigned DEFAULT NULL,
  `claimed_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `received_payments_dedup_hash_unique` (`dedup_hash`),
  KEY `received_payments_provider_index` (`provider`),
  KEY `received_payments_device_id_index` (`device_id`),
  KEY `received_payments_status_index` (`status`),
  KEY `received_payments_matched_document_id_index` (`matched_document_id`),
  KEY `received_payments_matched_document_payment_id_index` (`matched_document_payment_id`),
  KEY `received_payments_claimed_by_user_id_index` (`claimed_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `received_payments`');
    }
};

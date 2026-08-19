<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `users`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `email`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `email_verified_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `password`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `api_token`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `type`: enum('admin','seller','integrator','client'); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `active`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `is_multi_user`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `multi_user_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `permission_force_send_by_summary`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `permission_edit_cpe`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `permission_edit_item_prices`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `create_payment`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `delete_purchase`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `annular_purchase`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `edit_purchase`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `delete_payment`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `secret_login_time`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `recreate_documents`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `locked`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `bot_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `from_guest_register`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `multiple_default_document_types`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `photo_filename`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `position`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `contract_date`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `date_of_birth`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `corporate_cell_phone`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `personal_cell_phone`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `corporate_email`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `personal_email`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `last_names`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `names`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `identity_document_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `address`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `telephone`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `remember_token`: varchar(100); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `last_password_update`: datetime; NULL — Sin comentario definido en el esquema fuente.
 * - `restaurant_role_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `restaurant_pin`: varchar(4); NULL; DEFAULT 1111; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `document_id`: char(255); NULL; COLLATE utf8mb4_unicode_ci — Relacion con tipo de documentos
 * - `series_id`: int(10) unsigned; NULL — Relacion con series
 * - `zone_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `permission_edit_sale_note`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `change_seller`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `establishment_id` int(10) unsigned DEFAULT NULL,
  `type` enum('admin','seller','integrator','client') COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_multi_user` tinyint(1) NOT NULL DEFAULT '0',
  `multi_user_id` int(10) unsigned DEFAULT NULL,
  `permission_force_send_by_summary` tinyint(1) NOT NULL DEFAULT '0',
  `permission_edit_cpe` tinyint(1) NOT NULL DEFAULT '0',
  `permission_edit_item_prices` tinyint(1) NOT NULL DEFAULT '1',
  `create_payment` tinyint(1) NOT NULL DEFAULT '1',
  `delete_purchase` tinyint(1) NOT NULL DEFAULT '1',
  `annular_purchase` tinyint(1) NOT NULL DEFAULT '1',
  `edit_purchase` tinyint(1) NOT NULL DEFAULT '1',
  `delete_payment` tinyint(1) NOT NULL DEFAULT '1',
  `secret_login_time` timestamp NULL DEFAULT NULL,
  `recreate_documents` tinyint(1) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `bot_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `from_guest_register` tinyint(1) NOT NULL DEFAULT '0',
  `multiple_default_document_types` tinyint(1) NOT NULL DEFAULT '0',
  `photo_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_date` date DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `corporate_cell_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personal_cell_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `corporate_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personal_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_names` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `names` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_password_update` datetime DEFAULT NULL,
  `restaurant_role_id` int(10) unsigned DEFAULT NULL,
  `restaurant_pin` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT '1111',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `document_id` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relacion con tipo de documentos',
  `series_id` int(10) unsigned DEFAULT NULL COMMENT 'Relacion con series',
  `zone_id` int(10) unsigned DEFAULT NULL,
  `permission_edit_sale_note` tinyint(1) NOT NULL DEFAULT '0',
  `change_seller` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_api_token_unique` (`api_token`),
  KEY `users_establishment_id_foreign` (`establishment_id`),
  KEY `users_restaurant_role_id_foreign` (`restaurant_role_id`),
  KEY `users_is_multi_user_index` (`is_multi_user`),
  KEY `users_active_index` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `users`');
    }
};
// ######### FIN CAMBIO NELSON #########

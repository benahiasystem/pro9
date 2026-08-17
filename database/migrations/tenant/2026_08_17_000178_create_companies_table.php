<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `companies`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `identity_document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `trade_name`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_send_id`: char(2); NOT NULL; DEFAULT 01; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_type_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_username`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_password`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_url`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `certificate`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `certificate_due`: date; NULL — Sin comentario definido en el esquema fuente.
 * - `logo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `logo_dark`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `detraction_account`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `app_logo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `logo_store`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `favicon`: varchar(150); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `claims_widget_custom_url`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `claims_widget_custom_url_active`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `img_firm`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `operation_amazonia`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `integrated_query_client_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `integrated_query_client_secret`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `url_login_pse`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `pse_provider_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `user_pse`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `password_pse`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `client_id_pse`: varchar(255); NOT NULL; DEFAULT 8; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `send_document_to_pse`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `url_send_cdr_pse`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `url_signature_pse`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `ws_api_phone_number_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `ws_api_token`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_sunat_username`: varchar(20); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `soap_sunat_password`: varchar(20); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `api_sunat_id`: varchar(36); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `api_sunat_secret`: varchar(50); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `title_web`: varchar(255); NOT NULL; DEFAULT Facturación Electrónica; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `cod_digemid`: text; NULL; COLLATE utf8mb4_unicode_ci — Codigo de establecimiento DIGEMID
 * - `sire_client_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sire_client_secret`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sire_username`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `sire_password`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `digital_certificate_qztray`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `private_certificate_qztray`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `mtc_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `pse_username`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `pse_password`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `pse_token`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `qr_api_enable_ws`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `qr_api_url_ws`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `qr_api_key_ws`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `security_code`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name_person_support_contaweb`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `telephone_support_contaweb`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `email_support_contaweb`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_send_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01',
  `soap_type_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `soap_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_due` date DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_dark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detraction_account` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_store` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claims_widget_custom_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claims_widget_custom_url_active` tinyint(1) NOT NULL DEFAULT '0',
  `img_firm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operation_amazonia` tinyint(1) NOT NULL DEFAULT '0',
  `integrated_query_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `integrated_query_client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_login_pse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pse_provider_id` int(10) unsigned DEFAULT NULL,
  `user_pse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_pse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id_pse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '8',
  `send_document_to_pse` tinyint(1) NOT NULL DEFAULT '0',
  `url_send_cdr_pse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_signature_pse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ws_api_phone_number_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ws_api_token` text COLLATE utf8mb4_unicode_ci,
  `soap_sunat_username` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soap_sunat_password` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_sunat_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_sunat_secret` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_web` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Facturación Electrónica',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cod_digemid` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de establecimiento DIGEMID',
  `sire_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sire_client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sire_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sire_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digital_certificate_qztray` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `private_certificate_qztray` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mtc_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pse_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pse_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pse_token` text COLLATE utf8mb4_unicode_ci,
  `qr_api_enable_ws` tinyint(1) NOT NULL DEFAULT '0',
  `qr_api_url_ws` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_key_ws` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_person_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_identity_document_type_id_foreign` (`identity_document_type_id`),
  KEY `companies_soap_type_id_foreign` (`soap_type_id`),
  KEY `companies_pse_provider_id_foreign` (`pse_provider_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `companies`');
    }
};

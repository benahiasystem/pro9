<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `companies` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `trade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `fiscal_emission_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `fiscal_configuration` text COLLATE utf8mb4_unicode_ci
 * - `fiscal_credentials` text COLLATE utf8mb4_unicode_ci
 * - `fiscal_environment_locked` tinyint(1) NOT NULL DEFAULT '0'
 * - `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `logo_dark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `app_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `logo_store` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `favicon` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `claims_widget_custom_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `claims_widget_custom_url_active` tinyint(1) NOT NULL DEFAULT '0'
 * - `img_firm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `integrated_query_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `integrated_query_client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `ws_api_phone_number_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `ws_api_token` text COLLATE utf8mb4_unicode_ci
 * - `title_web` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Facturación Electrónica'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `cod_digemid` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de establecimiento DIGEMID'
 * - `digital_certificate_qztray` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `private_certificate_qztray` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `mtc_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_enable_ws` tinyint(1) NOT NULL DEFAULT '0'
 * - `qr_api_url_ws` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `qr_api_key_ws` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `security_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `name_person_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `telephone_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `email_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 */
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
  `fiscal_environment` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_emission_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_configuration` text COLLATE utf8mb4_unicode_ci,
  `fiscal_credentials` text COLLATE utf8mb4_unicode_ci,
  `fiscal_environment_locked` tinyint(1) NOT NULL DEFAULT '0',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_dark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_store` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claims_widget_custom_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claims_widget_custom_url_active` tinyint(1) NOT NULL DEFAULT '0',
  `img_firm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `integrated_query_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `integrated_query_client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ws_api_phone_number_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ws_api_token` text COLLATE utf8mb4_unicode_ci,
  `title_web` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Facturación Electrónica',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cod_digemid` text COLLATE utf8mb4_unicode_ci COMMENT 'Codigo de establecimiento DIGEMID',
  `digital_certificate_qztray` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `private_certificate_qztray` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mtc_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_enable_ws` tinyint(1) NOT NULL DEFAULT '0',
  `qr_api_url_ws` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_api_key_ws` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_person_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_support_contaweb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_identity_document_type_id_foreign` (`identity_document_type_id`),
  KEY `companies_fiscal_environment_foreign` (`fiscal_environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `companies`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

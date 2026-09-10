<?php
// ######### INICIO CAMBIO NELSON #########

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
 * - `fiscal_environment`: varchar(16); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `fiscal_emission_mode`: varchar(32); NOT NULL — Modalidad seleccionada al crear el tenant.
 * - `fiscal_configuration`: text; NULL — Parámetros opcionales de la modalidad.
 * - `fiscal_credentials`: text; NULL — Credenciales cifradas del proveedor.
 * - `fiscal_environment_locked`: tinyint(1); NOT NULL; DEFAULT 0 — Bloqueo permanente después de operar.
 * - `logo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `logo_dark`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `app_logo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `logo_store`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `favicon`: varchar(150); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `claims_widget_custom_url`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `claims_widget_custom_url_active`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `img_firm`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `operation_amazonia`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `integrated_query_client_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `integrated_query_client_secret`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `ws_api_phone_number_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `ws_api_token`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
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
  `operation_amazonia` tinyint(1) NOT NULL DEFAULT '0',
  `integrated_query_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `integrated_query_client_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ws_api_phone_number_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ws_api_token` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `companies`');
    }
};
// ######### FIN CAMBIO NELSON #########

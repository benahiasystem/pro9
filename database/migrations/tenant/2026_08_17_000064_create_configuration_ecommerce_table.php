<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `configuration_ecommerce`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `information_contact_name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `information_contact_email`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `information_contact_phone`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `information_contact_address`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `phone_whatsapp`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `script_paypal`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `logo`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `link_youtube`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `link_twitter`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `link_tiktok`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `link_instagram`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `color_ecommerce`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `link_facebook`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `tag_support`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `tag_dollar`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `tag_shipping`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `token_public_culqui`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `token_private_culqui`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `title_one_customised_link`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `title_two_customised_link`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `title_three_customised_link`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `customised_link_one`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `customised_link_two`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `customised_link_three`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `preferences`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `publicidad_activa`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `publicidad_texto`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `publicidad_color_fondo`: varchar(255); NOT NULL; DEFAULT #000000; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `publicidad_link`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `terms_conditions`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `privacy_policy`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `about_us`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `delivery_no_coverage_message`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `enable_electronic_documents`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enable_store_pickup`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enable_yape`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enable_transfer`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `configuration_ecommerce` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `information_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `information_contact_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `information_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `information_contact_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_whatsapp` text COLLATE utf8mb4_unicode_ci,
  `script_paypal` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_youtube` text COLLATE utf8mb4_unicode_ci,
  `link_twitter` text COLLATE utf8mb4_unicode_ci,
  `link_tiktok` text COLLATE utf8mb4_unicode_ci,
  `link_instagram` text COLLATE utf8mb4_unicode_ci,
  `color_ecommerce` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_facebook` text COLLATE utf8mb4_unicode_ci,
  `tag_support` text COLLATE utf8mb4_unicode_ci,
  `tag_dollar` text COLLATE utf8mb4_unicode_ci,
  `tag_shipping` text COLLATE utf8mb4_unicode_ci,
  `token_public_culqui` text COLLATE utf8mb4_unicode_ci,
  `token_private_culqui` text COLLATE utf8mb4_unicode_ci,
  `title_one_customised_link` text COLLATE utf8mb4_unicode_ci,
  `title_two_customised_link` text COLLATE utf8mb4_unicode_ci,
  `title_three_customised_link` text COLLATE utf8mb4_unicode_ci,
  `customised_link_one` text COLLATE utf8mb4_unicode_ci,
  `customised_link_two` text COLLATE utf8mb4_unicode_ci,
  `customised_link_three` text COLLATE utf8mb4_unicode_ci,
  `preferences` json DEFAULT NULL,
  `publicidad_activa` tinyint(1) NOT NULL DEFAULT '0',
  `publicidad_texto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publicidad_color_fondo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#000000',
  `publicidad_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_conditions` longtext COLLATE utf8mb4_unicode_ci,
  `privacy_policy` longtext COLLATE utf8mb4_unicode_ci,
  `about_us` longtext COLLATE utf8mb4_unicode_ci,
  `delivery_no_coverage_message` text COLLATE utf8mb4_unicode_ci,
  `enable_electronic_documents` tinyint(1) NOT NULL DEFAULT '0',
  `enable_store_pickup` tinyint(1) NOT NULL DEFAULT '0',
  `enable_yape` tinyint(1) NOT NULL DEFAULT '0',
  `enable_transfer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `configuration_ecommerce`');
    }
};
// ######### FIN CAMBIO NELSON #########

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `configuration_ecommerce` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `information_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `information_contact_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `information_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `information_contact_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `phone_whatsapp` text COLLATE utf8mb4_unicode_ci
 * - `script_paypal` text COLLATE utf8mb4_unicode_ci
 * - `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `link_youtube` text COLLATE utf8mb4_unicode_ci
 * - `link_twitter` text COLLATE utf8mb4_unicode_ci
 * - `link_tiktok` text COLLATE utf8mb4_unicode_ci
 * - `link_instagram` text COLLATE utf8mb4_unicode_ci
 * - `color_ecommerce` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `link_facebook` text COLLATE utf8mb4_unicode_ci
 * - `tag_support` text COLLATE utf8mb4_unicode_ci
 * - `tag_dollar` text COLLATE utf8mb4_unicode_ci
 * - `tag_shipping` text COLLATE utf8mb4_unicode_ci
 * - `token_public_culqui` text COLLATE utf8mb4_unicode_ci
 * - `token_private_culqui` text COLLATE utf8mb4_unicode_ci
 * - `title_one_customised_link` text COLLATE utf8mb4_unicode_ci
 * - `title_two_customised_link` text COLLATE utf8mb4_unicode_ci
 * - `title_three_customised_link` text COLLATE utf8mb4_unicode_ci
 * - `customised_link_one` text COLLATE utf8mb4_unicode_ci
 * - `customised_link_two` text COLLATE utf8mb4_unicode_ci
 * - `customised_link_three` text COLLATE utf8mb4_unicode_ci
 * - `preferences` json DEFAULT NULL
 * - `publicidad_activa` tinyint(1) NOT NULL DEFAULT '0'
 * - `publicidad_texto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `publicidad_color_fondo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#000000'
 * - `publicidad_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `terms_conditions` longtext COLLATE utf8mb4_unicode_ci
 * - `privacy_policy` longtext COLLATE utf8mb4_unicode_ci
 * - `about_us` longtext COLLATE utf8mb4_unicode_ci
 * - `delivery_no_coverage_message` text COLLATE utf8mb4_unicode_ci
 * - `enable_electronic_documents` tinyint(1) NOT NULL DEFAULT '0'
 * - `enable_store_pickup` tinyint(1) NOT NULL DEFAULT '0'
 * - `quotation_enabled` tinyint(1) NOT NULL DEFAULT '0'
 * - `quotation_mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'quote_and_sell'
 * - `quotation_show_prices` tinyint(1) NOT NULL DEFAULT '1'
 * - `quotation_success_message` text COLLATE utf8mb4_unicode_ci
 * - `quotation_validity_days` tinyint(3) unsigned NOT NULL DEFAULT '7'
 * - `quotation_terms` text COLLATE utf8mb4_unicode_ci
 * - `enable_yape` tinyint(1) NOT NULL DEFAULT '0'
 * - `enable_transfer` tinyint(1) NOT NULL DEFAULT '0'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 */
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
  `quotation_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `quotation_mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'quote_and_sell',
  `quotation_show_prices` tinyint(1) NOT NULL DEFAULT '1',
  `quotation_success_message` text COLLATE utf8mb4_unicode_ci,
  `quotation_validity_days` tinyint(3) unsigned NOT NULL DEFAULT '7',
  `quotation_terms` text COLLATE utf8mb4_unicode_ci,
  `enable_yape` tinyint(1) NOT NULL DEFAULT '0',
  `enable_transfer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `configuration_ecommerce`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

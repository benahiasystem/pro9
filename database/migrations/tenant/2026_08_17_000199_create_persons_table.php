<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `persons` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` int(10) unsigned NOT NULL AUTO_INCREMENT
 * - `type` enum('customers','suppliers') COLLATE utf8mb4_unicode_ci NOT NULL
 * - `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `trade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `internal_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `barcode` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `nationality_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `address_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000'
 * - `condition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `accumulated_points` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'sistema por puntos'
 * - `perception_agent` tinyint(1) NOT NULL DEFAULT '0'
 * - `is_agent_retention` tinyint(1) NOT NULL DEFAULT '0'
 * - `person_type_id` int(10) unsigned DEFAULT NULL
 * - `contact` json DEFAULT NULL
 * - `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 * - `percentage_perception` decimal(12,2) DEFAULT NULL
 * - `enabled` tinyint(1) NOT NULL DEFAULT '1'
 * - `website` text COLLATE utf8mb4_unicode_ci COMMENT 'Sitio Web'
 * - `zone` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Zona'
 * - `observation` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones'
 * - `created_at` timestamp NULL DEFAULT NULL
 * - `updated_at` timestamp NULL DEFAULT NULL
 * - `status` tinyint(4) NOT NULL DEFAULT '1'
 * - `credit_days` int(10) unsigned DEFAULT '0' COMMENT 'establece los dias de credito'
 * - `optional_email` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Conjunto de correos de envio'
 * - `parent_id` int(10) unsigned DEFAULT '0' COMMENT 'Se relaciona con si mismo, numero mayor a 0 es el id del padre.'
 * - `zone_id` int(10) unsigned DEFAULT NULL
 * - `seller_id` int(10) unsigned DEFAULT NULL
 * - `has_discount` tinyint(1) NOT NULL DEFAULT '0'
 * - `discount_type` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01'
 * - `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00'
 * - `text_filter` longtext COLLATE utf8mb4_unicode_ci
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `persons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('customers','suppliers') COLLATE utf8mb4_unicode_ci NOT NULL,
  `identity_document_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district_id` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_type_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `establishment_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000',
  `condition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accumulated_points` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'sistema por puntos',
  `perception_agent` tinyint(1) NOT NULL DEFAULT '0',
  `is_agent_retention` tinyint(1) NOT NULL DEFAULT '0',
  `person_type_id` int(10) unsigned DEFAULT NULL,
  `contact` json DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage_perception` decimal(12,2) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `website` text COLLATE utf8mb4_unicode_ci COMMENT 'Sitio Web',
  `zone` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Zona',
  `observation` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `credit_days` int(10) unsigned DEFAULT '0' COMMENT 'establece los dias de credito',
  `optional_email` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Conjunto de correos de envio',
  `parent_id` int(10) unsigned DEFAULT '0' COMMENT 'Se relaciona con si mismo, numero mayor a 0 es el id del padre.',
  `zone_id` int(10) unsigned DEFAULT NULL,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `has_discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_type` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `text_filter` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `persons_identity_document_type_id_foreign` (`identity_document_type_id`),
  KEY `persons_country_id_foreign` (`country_id`),
  KEY `persons_department_id_foreign` (`department_id`),
  KEY `persons_province_id_foreign` (`province_id`),
  KEY `persons_district_id_foreign` (`district_id`),
  KEY `persons_name_index` (`name`),
  KEY `persons_number_index` (`number`),
  KEY `persons_type_index` (`type`),
  KEY `persons_person_type_id_foreign` (`person_type_id`),
  KEY `persons_enabled_index` (`enabled`),
  KEY `persons_address_type_id_foreign` (`address_type_id`),
  KEY `persons_seller_id_index` (`seller_id`),
  KEY `persons_nationality_id_foreign` (`nationality_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `persons`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

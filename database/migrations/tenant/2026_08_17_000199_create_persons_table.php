<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `persons`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `type`: enum('customers','suppliers'); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `identity_document_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `number`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `name`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `trade_name`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `internal_code`: varchar(100); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `barcode`: varchar(150); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `country_id`: char(2); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `nationality_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `department_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `province_id`: char(4); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `district_id`: char(6); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `address_type_id`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `address`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `establishment_code`: varchar(4); NOT NULL; DEFAULT 0000; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `condition`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `state`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `email`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `password`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `remember_token`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `telephone`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `accumulated_points`: decimal(12,2); NOT NULL; DEFAULT 0.00 — sistema por puntos
 * - `perception_agent`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `is_agent_retention`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `person_type_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `contact`: json; NULL — Sin comentario definido en el esquema fuente.
 * - `comment`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `percentage_perception`: decimal(12,2); NULL — Sin comentario definido en el esquema fuente.
 * - `enabled`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `website`: text; NULL; COLLATE utf8mb4_unicode_ci — Sitio Web
 * - `zone`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Zona
 * - `observation`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Observaciones
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `status`: tinyint(4); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `credit_days`: int(10) unsigned; NULL; DEFAULT 0 — establece los dias de credito
 * - `optional_email`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Conjunto de correos de envio
 * - `parent_id`: int(10) unsigned; NULL; DEFAULT 0 — Se relaciona con si mismo, numero mayor a 0 es el id del padre.
 * - `zone_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `seller_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `has_discount`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `discount_type`: char(2); NOT NULL; DEFAULT 01; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `discount_amount`: decimal(12,2); NOT NULL; DEFAULT 0.00 — Sin comentario definido en el esquema fuente.
 * - `text_filter`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `persons`');
    }
};
// ######### FIN CAMBIO NELSON #########

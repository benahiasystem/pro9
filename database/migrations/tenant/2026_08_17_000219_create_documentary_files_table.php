<?php

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `documentary_files`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `user_id`: int(10) unsigned; NULL — Sin comentario definido en el esquema fuente.
 * - `documentary_document_id`: int(10) unsigned; NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `documentary_process_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `number`: mediumtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `year`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `invoice`: longtext; NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `date_register`: varchar(10); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `time_register`: varchar(8); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `person_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `sender`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `subject`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `attached_file`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `observation`: text; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `status`: enum('RECIBIDO','DERIVADO','FINALIZADO','ARCHIVADO'); NOT NULL; DEFAULT RECIBIDO; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `documentary_office_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Define el ultimo proceso
 * - `created_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `updated_at`: timestamp; NULL — Sin comentario definido en el esquema fuente.
 * - `requirements`: longtext; NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `is_archive`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — define si el tramite es simplificado
 * - `is_simplify`: tinyint(3) unsigned; NOT NULL; DEFAULT 0 — define si el tramite es simplificado
 * - `documentary_guides_number_status_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Cuando es simplificado, se usará este status
 * - `is_completed`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `date_end`: datetime; NULL — Fecha de finalización
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `documentary_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `documentary_document_id` int(10) unsigned DEFAULT '0',
  `documentary_process_id` int(10) unsigned NOT NULL,
  `number` mediumtext COLLATE utf8mb4_unicode_ci,
  `year` int(10) unsigned NOT NULL,
  `invoice` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_register` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_register` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `person_id` int(10) unsigned NOT NULL,
  `sender` longtext COLLATE utf8mb4_unicode_ci,
  `subject` longtext COLLATE utf8mb4_unicode_ci,
  `attached_file` longtext COLLATE utf8mb4_unicode_ci,
  `observation` text COLLATE utf8mb4_unicode_ci,
  `status` enum('RECIBIDO','DERIVADO','FINALIZADO','ARCHIVADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECIBIDO',
  `documentary_office_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Define el ultimo proceso',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `requirements` longtext COLLATE utf8mb4_unicode_ci,
  `is_archive` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'define si el tramite es simplificado',
  `is_simplify` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'define si el tramite es simplificado',
  `documentary_guides_number_status_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Cuando es simplificado, se usará este status',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `establishment_id` int(10) unsigned NOT NULL DEFAULT '0',
  `date_end` datetime DEFAULT NULL COMMENT 'Fecha de finalización',
  PRIMARY KEY (`id`),
  KEY `documentary_files_documentary_document_id_foreign` (`documentary_document_id`),
  KEY `documentary_files_documentary_process_id_foreign` (`documentary_process_id`),
  KEY `documentary_files_person_id_foreign` (`person_id`),
  KEY `documentary_files_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `documentary_files`');
    }
};

<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `price_labels`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `position`: tinyint(4); NOT NULL — Orden de visualización del precio
 * - `label`: varchar(50); NOT NULL; COLLATE utf8mb4_unicode_ci — Etiqueta personalizada del precio (ej: Precio Mayorista)
 * - `is_active`: tinyint(1); NOT NULL; DEFAULT 1 — Si está activo para usar en ventas
 * - `is_default`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `price_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `position` tinyint(4) NOT NULL COMMENT 'Orden de visualización del precio',
  `label` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Etiqueta personalizada del precio (ej: Precio Mayorista)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Si está activo para usar en ventas',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `price_labels_position_unique` (`position`),
  KEY `price_labels_is_active_position_index` (`is_active`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `price_labels`');
    }
};
// ######### FIN CAMBIO NELSON #########

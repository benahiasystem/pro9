<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `template_columns_config`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `establishment_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `template_name`: varchar(255); NOT NULL; DEFAULT Plantilla_personalizable; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `columns_config`: json; NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `template_columns_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `establishment_id` int(10) unsigned NOT NULL,
  `template_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Plantilla_personalizable',
  `columns_config` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_columns_config_establishment_id_template_name_unique` (`establishment_id`,`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `template_columns_config`');
    }
};
// ######### FIN CAMBIO NELSON #########

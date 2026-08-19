<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `transaction_queries`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `date`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `time`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `response`: json; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `transaction_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
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
CREATE TABLE `transaction_queries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `response` json NOT NULL,
  `transaction_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_queries_transaction_id_foreign` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `transaction_queries`');
    }
};
// ######### FIN CAMBIO NELSON #########

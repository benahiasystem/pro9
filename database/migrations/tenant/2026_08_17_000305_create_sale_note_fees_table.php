<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `sale_note_fees`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `sale_note_id`: int(10) unsigned; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `date`: date; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `currency_type_id`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `amount`: decimal(12,2); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `payment_method_type_id`: char(2); NULL; COLLATE utf8mb4_unicode_ci — Relacion con el metodo de pago, Nulo es pago a cuotas
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `sale_note_fees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_note_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method_type_id` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relacion con el metodo de pago, Nulo es pago a cuotas',
  PRIMARY KEY (`id`),
  KEY `sale_note_fees_sale_note_id_foreign` (`sale_note_id`),
  KEY `sale_note_fees_currency_type_id_foreign` (`currency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `sale_note_fees`');
    }
};
// ######### FIN CAMBIO NELSON #########

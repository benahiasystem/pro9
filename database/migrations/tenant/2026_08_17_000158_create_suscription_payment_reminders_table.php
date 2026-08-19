<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `suscription_payment_reminders`.
 *
 * Inventario de columnas:
 * - `id`: bigint(20) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `reminder_days`: int(11); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `reminder_type`: enum('before','same_day','after'); NOT NULL; DEFAULT before; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `reminder_time`: time; NOT NULL — Sin comentario definido en el esquema fuente.
 * - `shipping_medium`: enum('email','whatsapp'); NOT NULL; DEFAULT email; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `suscription_payment_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reminder_days` int(11) NOT NULL DEFAULT '0',
  `reminder_type` enum('before','same_day','after') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'before',
  `reminder_time` time NOT NULL,
  `shipping_medium` enum('email','whatsapp') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `suscription_payment_reminders`');
    }
};
// ######### FIN CAMBIO NELSON #########

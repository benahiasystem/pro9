<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `payment_method_types` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `has_card` tinyint(1) NOT NULL DEFAULT '0'
 * - `charge` decimal(12,2) DEFAULT NULL
 * - `number_days` int(11) DEFAULT NULL
 * - `is_credit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si es tipo credito'
 * - `is_cash` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si es es efectivo'
 * - `is_active` tinyint(1) NOT NULL DEFAULT '1'
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_method_types` (
  `id` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_card` tinyint(1) NOT NULL DEFAULT '0',
  `charge` decimal(12,2) DEFAULT NULL,
  `number_days` int(11) DEFAULT NULL,
  `is_credit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si es tipo credito',
  `is_cash` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Define si es es efectivo',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `payment_method_types_id_index` (`id`),
  KEY `payment_method_types_is_credit_index` (`is_credit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_method_types`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

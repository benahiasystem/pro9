<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `transaction_states` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `success` tinyint(1) NOT NULL
 * - `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `status_detail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `original_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `user_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `transaction_states` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_detail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_states_name_index` (`name`),
  KEY `transaction_states_success_index` (`success`),
  KEY `transaction_states_status_index` (`status`),
  KEY `transaction_states_status_detail_index` (`status_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `transaction_states`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

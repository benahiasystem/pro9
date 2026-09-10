<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO ESQUEMA INICIAL VENEZUELA ########
/**
 * Estructura inicial de `client_errors` para instalaciones nuevas.
 * Inventario de columnas:
 * - `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT
 * - `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `client_error_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `original_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 * - `user_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `client_errors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_error_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_errors_client_error_type_id_foreign` (`client_error_type_id`),
  KEY `client_errors_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `client_errors`');
    }
};
// ######## FIN ESQUEMA INICIAL VENEZUELA ########

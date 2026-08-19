<?php
// ######### INICIO CAMBIO NELSON #########

/**
 * Estructura efectiva clonada desde `tenancy_bbc`.
 * Tabla: `restaurant_configurations`.
 *
 * Inventario de columnas:
 * - `id`: int(10) unsigned; NOT NULL; auto_increment — Sin comentario definido en el esquema fuente.
 * - `menu_pos`: tinyint(1); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `menu_order`: tinyint(1); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `menu_tables`: tinyint(1); NOT NULL — Sin comentario definido en el esquema fuente.
 * - `first_menu`: varchar(255); NOT NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `tables_quantity`: int(11); NOT NULL; DEFAULT 15 — Sin comentario definido en el esquema fuente.
 * - `menu_bar`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `menu_kitchen`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `items_maintenance`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_environment_1`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `enabled_environment_2`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `tables_quantity_environment_2`: int(11); NOT NULL; DEFAULT 5 — Sin comentario definido en el esquema fuente.
 * - `enabled_environment_3`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `tables_quantity_environment_3`: int(11); NOT NULL; DEFAULT 5 — Sin comentario definido en el esquema fuente.
 * - `enabled_environment_4`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `tables_quantity_environment_4`: int(11); NOT NULL; DEFAULT 5 — Sin comentario definido en el esquema fuente.
 * - `enabled_send_command`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_print_command`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `enabled_print_group_commands`: tinyint(4); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_printsend_command`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_command_waiter`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_pos_waiter`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_close_table`: tinyint(1); NOT NULL; DEFAULT 1 — Sin comentario definido en el esquema fuente.
 * - `enabled_close_table_mozo`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `enabled_server_print`: tinyint(1); NOT NULL; DEFAULT 0 — Habilita el stream de impresión por SSE
 * - `replace_template_mozo`: tinyint(1); NOT NULL; DEFAULT 0 — Habilita la impresión de la plantilla del facturador en lugar de la de mozo
 * - `printer_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `printer_host`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `printer_status`: varchar(50); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `printer_public_ip`: varchar(45); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `print_local_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `print_destination`: tinyint(1); NOT NULL; DEFAULT 1 — false = Directo a BuhoPrinter local | true = Centralizado vía backend/Redis
 * - `printer_name_comanda`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `printer_name_documents`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `printer_name_precuenta`: varchar(255); NULL; COLLATE utf8mb4_unicode_ci — Sin comentario definido en el esquema fuente.
 * - `printer_areas_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 * - `printer_per_area_enabled`: tinyint(1); NOT NULL; DEFAULT 0 — Sin comentario definido en el esquema fuente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `restaurant_configurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_pos` tinyint(1) NOT NULL,
  `menu_order` tinyint(1) NOT NULL,
  `menu_tables` tinyint(1) NOT NULL,
  `first_menu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tables_quantity` int(11) NOT NULL DEFAULT '15',
  `menu_bar` tinyint(1) NOT NULL DEFAULT '1',
  `menu_kitchen` tinyint(1) NOT NULL DEFAULT '1',
  `items_maintenance` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_environment_1` tinyint(1) NOT NULL DEFAULT '1',
  `enabled_environment_2` tinyint(1) NOT NULL DEFAULT '0',
  `tables_quantity_environment_2` int(11) NOT NULL DEFAULT '5',
  `enabled_environment_3` tinyint(1) NOT NULL DEFAULT '0',
  `tables_quantity_environment_3` int(11) NOT NULL DEFAULT '5',
  `enabled_environment_4` tinyint(1) NOT NULL DEFAULT '0',
  `tables_quantity_environment_4` int(11) NOT NULL DEFAULT '5',
  `enabled_send_command` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_print_command` tinyint(1) NOT NULL DEFAULT '1',
  `enabled_print_group_commands` tinyint(4) NOT NULL DEFAULT '0',
  `enabled_printsend_command` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_command_waiter` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_pos_waiter` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_close_table` tinyint(1) NOT NULL DEFAULT '1',
  `enabled_close_table_mozo` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_server_print` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Habilita el stream de impresión por SSE',
  `replace_template_mozo` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Habilita la impresión de la plantilla del facturador en lugar de la de mozo',
  `printer_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `printer_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printer_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printer_public_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `print_local_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `print_destination` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'false = Directo a BuhoPrinter local | true = Centralizado vía backend/Redis',
  `printer_name_comanda` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printer_name_documents` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printer_name_precuenta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printer_areas_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `printer_per_area_enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `restaurant_configurations`');
    }
};
// ######### FIN CAMBIO NELSON #########

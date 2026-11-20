<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - menu_pos: boolean
 * - menu_order: boolean
 * - menu_tables: boolean
 * - first_menu: varchar(255)
 * - tables_quantity: integer
 * - menu_bar: boolean
 * - menu_kitchen: boolean
 * - items_maintenance: boolean
 * - enabled_environment_1: boolean
 * - enabled_environment_2: boolean
 * - tables_quantity_environment_2: integer
 * - enabled_environment_3: boolean
 * - tables_quantity_environment_3: integer
 * - enabled_environment_4: boolean
 * - tables_quantity_environment_4: integer
 * - enabled_send_command: boolean
 * - enabled_print_command: boolean
 * - enabled_print_group_commands: smallint
 * - enabled_printsend_command: boolean
 * - enabled_command_waiter: boolean
 * - enabled_pos_waiter: boolean
 * - enabled_close_table: boolean
 * - enabled_close_table_mozo: boolean
 * - enabled_server_print: boolean
 * - replace_template_mozo: boolean
 * - printer_enabled: boolean
 * - printer_host: varchar(255)
 * - printer_status: varchar(50)
 * - printer_public_ip: varchar(45)
 * - print_local_enabled: boolean
 * - print_destination: boolean
 * - printer_name_comanda: varchar(255)
 * - printer_name_documents: varchar(255)
 * - printer_name_precuenta: varchar(255)
 * - printer_areas_enabled: boolean
 * - printer_per_area_enabled: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('menu_pos');
            $table->boolean('menu_order');
            $table->boolean('menu_tables');
            $table->string('first_menu', 255)->collation('sab_ci_ai');
            $table->integer('tables_quantity')->default('15');
            $table->boolean('menu_bar')->default(true);
            $table->boolean('menu_kitchen')->default(true);
            $table->boolean('items_maintenance')->default(false);
            $table->boolean('enabled_environment_1')->default(true);
            $table->boolean('enabled_environment_2')->default(false);
            $table->integer('tables_quantity_environment_2')->default('5');
            $table->boolean('enabled_environment_3')->default(false);
            $table->integer('tables_quantity_environment_3')->default('5');
            $table->boolean('enabled_environment_4')->default(false);
            $table->integer('tables_quantity_environment_4')->default('5');
            $table->boolean('enabled_send_command')->default(false);
            $table->boolean('enabled_print_command')->default(true);
            $table->smallInteger('enabled_print_group_commands')->default('0');
            $table->boolean('enabled_printsend_command')->default(false);
            $table->boolean('enabled_command_waiter')->default(false);
            $table->boolean('enabled_pos_waiter')->default(false);
            $table->boolean('enabled_close_table')->default(true);
            $table->boolean('enabled_close_table_mozo')->default(false);
            $table->boolean('enabled_server_print')->default(false)->comment('Habilita el stream de impresión por SSE');
            $table->boolean('replace_template_mozo')->default(false)->comment('Habilita la impresión de la plantilla del facturador en lugar de la de mozo');
            $table->boolean('printer_enabled')->default(false);
            $table->string('printer_host', 255)->collation('sab_ci_ai')->nullable();
            $table->string('printer_status', 50)->collation('sab_ci_ai')->nullable();
            $table->string('printer_public_ip', 45)->collation('sab_ci_ai')->nullable();
            $table->boolean('print_local_enabled')->default(false);
            $table->boolean('print_destination')->default(true)->comment('false = Directo a BuhoPrinter local | true = Centralizado vía backend/Redis');
            $table->string('printer_name_comanda', 255)->collation('sab_ci_ai')->nullable();
            $table->string('printer_name_documents', 255)->collation('sab_ci_ai')->nullable();
            $table->string('printer_name_precuenta', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('printer_areas_enabled')->default(false);
            $table->boolean('printer_per_area_enabled')->default(false);
        });
        DB::statement('ALTER TABLE "restaurant_configurations" ADD CONSTRAINT "restaurant_configurations_tables_quantity_range" CHECK ("tables_quantity" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "restaurant_configurations" ADD CONSTRAINT "restaurant_configurations_tables_quantity_environment_2_range" CHECK ("tables_quantity_environment_2" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "restaurant_configurations" ADD CONSTRAINT "restaurant_configurations_tables_quantity_environment_3_range" CHECK ("tables_quantity_environment_3" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "restaurant_configurations" ADD CONSTRAINT "restaurant_configurations_tables_quantity_environment_4_range" CHECK ("tables_quantity_environment_4" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "restaurant_configurations" ADD CONSTRAINT "restaurant_configurations_enabled_print_group_commands_range" CHECK ("enabled_print_group_commands" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

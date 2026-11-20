<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - show_image_item: boolean
 * - print_format_pdf: varchar(255)
 * - card_color: varchar(255)
 * - theme_color: varchar(255)
 * - header_waves: boolean
 * - direct_send_documents_whatsapp: boolean
 * - primary_color: varchar(255)
 * - direct_print: boolean
 * - app_mode: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('show_image_item')->default(true);
            $table->string('print_format_pdf', 255)->collation('pro9_ci_ai')->default('ticket');
            $table->string('card_color', 255)->collation('pro9_ci_ai')->default('multicolored');
            $table->string('theme_color', 255)->collation('pro9_ci_ai')->default('blue');
            $table->boolean('header_waves')->default(false);
            $table->boolean('direct_send_documents_whatsapp')->default(false);
            $table->string('primary_color', 255)->collation('pro9_ci_ai')->nullable()->default('#020F3C')->comment('Color principal para la nueva app movil - hexadecimal');
            $table->boolean('direct_print')->default(false);
            $table->string('app_mode', 255)->collation('pro9_ci_ai')->default('default');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

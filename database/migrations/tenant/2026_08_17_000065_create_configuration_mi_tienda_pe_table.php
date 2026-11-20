<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - establishment_id: bigint
 * - series_order_note_id: bigint
 * - series_document_ft_id: bigint
 * - user_id: bigint
 * - payment_destination_id: bigint
 * - currency_type_id: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - autogenerate: smallint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_mi_tienda_pe', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id')->nullable()->default('1');
            $table->bigInteger('series_order_note_id')->nullable()->default('0');
            $table->bigInteger('series_document_ft_id')->nullable()->default('0');
            $table->bigInteger('user_id')->nullable()->default('0');
            $table->bigInteger('payment_destination_id')->nullable()->default('0');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->smallInteger('autogenerate')->nullable()->default('0');
        });
        DB::statement('ALTER TABLE "configuration_mi_tienda_pe" ADD CONSTRAINT "configuration_mi_tienda_pe_establishment_id_range" CHECK ("establishment_id" >= 0)');
        DB::statement('ALTER TABLE "configuration_mi_tienda_pe" ADD CONSTRAINT "configuration_mi_tienda_pe_series_order_note_id_range" CHECK ("series_order_note_id" >= 0)');
        DB::statement('ALTER TABLE "configuration_mi_tienda_pe" ADD CONSTRAINT "configuration_mi_tienda_pe_series_document_ft_id_range" CHECK ("series_document_ft_id" >= 0)');
        DB::statement('ALTER TABLE "configuration_mi_tienda_pe" ADD CONSTRAINT "configuration_mi_tienda_pe_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "configuration_mi_tienda_pe" ADD CONSTRAINT "configuration_mi_tienda_pe_payment_destination_id_range" CHECK ("payment_destination_id" >= 0)');
        DB::statement('ALTER TABLE "configuration_mi_tienda_pe" ADD CONSTRAINT "configuration_mi_tienda_pe_autogenerate_range" CHECK ("autogenerate" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_mi_tienda_pe');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

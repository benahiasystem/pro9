<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - service: bigint
 * - date_of_issue: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_api_peru_services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('service')->nullable()->default('0')->comment('Tipo de servicio  1 => sunat/dni, 2 => validacion_multiple_cpe, 3 => CPE, 4 => tipo_de_cambio, 5 => printer_ticket');
            $table->timestamp('date_of_issue')->nullable();
        });
        DB::statement('ALTER TABLE "track_api_peru_services" ADD CONSTRAINT "track_api_peru_services_service_range" CHECK ("service" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('track_api_peru_services');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

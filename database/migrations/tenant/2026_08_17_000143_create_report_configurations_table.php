<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - route_name: varchar(255)
 * - route_path: varchar(255)
 * - name: varchar(255)
 * - convert_pen: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('route_name', 255)->collation('sab_ci_ai');
            $table->string('route_path', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->boolean('convert_pen')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['route_name'], 'report_configurations_route_name_unique');
            $table->unique(['route_path'], 'report_configurations_route_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

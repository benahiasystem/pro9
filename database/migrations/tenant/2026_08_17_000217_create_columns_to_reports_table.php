<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - report: varchar(255)
 * - columns: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('columns_to_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('report', 255)->collation('pro9_ci_ai')->nullable();
            $table->json('columns');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'columns_to_reports_user_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('columns_to_reports');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

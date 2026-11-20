<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - code: varchar(255)
 * - client_error_type_id: varchar(255)
 * - original_message: varchar(255)
 * - user_message: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 255)->collation('sab_ci_ai');
            $table->string('client_error_type_id', 255)->collation('sab_ci_ai');
            $table->string('original_message', 255)->collation('sab_ci_ai');
            $table->string('user_message', 255)->collation('sab_ci_ai');
            $table->index(['client_error_type_id'], 'client_errors_client_error_type_id_foreign');
            $table->index(['code'], 'client_errors_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_errors');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

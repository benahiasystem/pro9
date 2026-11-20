<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - internal_id: varchar(255)
 * - email: varchar(255)
 * - telephone: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('internal_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('telephone', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['internal_id'], 'agents_internal_id_unique');
            $table->index(['name'], 'agents_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

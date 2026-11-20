<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(50)
 * - description: varchar(250)
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50)->collation('sab_ci_ai');
            $table->string('description', 250)->collation('sab_ci_ai')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_documents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

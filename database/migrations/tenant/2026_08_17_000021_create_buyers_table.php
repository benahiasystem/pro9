<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(200)
 * - number: varchar(255)
 * - address: varchar(250)
 * - location_id: varchar(255)
 * - identity_document_type_id: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 200)->collation('pro9_ci_ai');
            $table->string('number', 255)->collation('pro9_ci_ai');
            $table->string('address', 250)->collation('pro9_ci_ai')->nullable();
            $table->string('location_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('identity_document_type_id', 255)->collation('pro9_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

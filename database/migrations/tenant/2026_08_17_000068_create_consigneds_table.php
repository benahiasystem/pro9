<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - identity_document_type_id: varchar(255)
 * - number: varchar(255)
 * - name: varchar(255)
 * - telephone: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consigneds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identity_document_type_id', 255)->collation('pro9_ci_ai');
            $table->string('number', 255)->collation('pro9_ci_ai');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('telephone', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consigneds');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

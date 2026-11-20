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
 * - address: varchar(255)
 * - number_mtc: varchar(12)
 * - is_default: boolean
 * - is_active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identity_document_type_id', 255)->collation('sab_ci_ai');
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('address', 255)->collation('sab_ci_ai');
            $table->string('number_mtc', 12)->collation('sab_ci_ai')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['identity_document_type_id'], 'dispatchers_identity_document_type_id_foreign');
            $table->index(['number'], 'dispatchers_number_index');
            $table->index(['name'], 'dispatchers_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatchers');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - document_type_id: varchar(255)
 * - series_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_default_document_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('document_type_id', 255)->collation('pro9_ci_ai');
            $table->bigInteger('series_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'user_default_document_types_user_id_foreign');
            $table->index(['document_type_id'], 'user_default_document_types_document_type_id_foreign');
            $table->index(['series_id'], 'user_default_document_types_series_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_default_document_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

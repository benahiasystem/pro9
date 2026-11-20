<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - selection_type: varchar(255)
 * - items: json
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('selection_type', 255)->collation('pro9_ci_ai')->default('single');
            $table->json('items')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

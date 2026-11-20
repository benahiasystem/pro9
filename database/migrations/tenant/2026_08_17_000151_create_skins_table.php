<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - filename: varchar(255)
 * - status: boolean
 * - is_system: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('filename', 255)->collation('pro9_ci_ai');
            $table->boolean('status')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skins');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

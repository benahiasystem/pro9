<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - code: varchar(255)
 * - name: varchar(255)
 * - description: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_roles');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

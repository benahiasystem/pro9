<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - plate_number: varchar(50)
 * - model: varchar(255)
 * - brand: varchar(255)
 * - tuc: varchar(255)
 * - is_default: boolean
 * - is_active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('plate_number', 50)->collation('sab_ci_ai');
            $table->string('model', 255)->collation('sab_ci_ai');
            $table->string('brand', 255)->collation('sab_ci_ai');
            $table->string('tuc', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transports');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

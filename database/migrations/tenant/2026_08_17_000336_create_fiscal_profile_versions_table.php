<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - company_id: bigint
 * - person_id: bigint
 * - effective_from: date
 * - profile: json
 * - actor_id: bigint
 * - created_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_profile_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->nullable();
            $table->bigInteger('person_id')->nullable();
            $table->date('effective_from');
            $table->json('profile');
            $table->bigInteger('actor_id');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'effective_from'], 'profile_company_date');
            $table->index(['person_id', 'effective_from'], 'profile_person_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_profile_versions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

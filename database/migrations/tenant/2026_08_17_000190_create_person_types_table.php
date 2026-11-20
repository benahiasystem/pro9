<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - price_label_id: bigint
 * - enabled_description_person_type: boolean
 * - description_person_type: text
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('description')->collation('pro9_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('price_label_id')->nullable();
            $table->boolean('enabled_description_person_type')->default(false);
            $table->text('description_person_type')->collation('pro9_ci_ai')->nullable();
            $table->index(['price_label_id'], 'person_types_price_label_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

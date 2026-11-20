<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: varchar(50)
 * - image: varchar(150)
 * - active: boolean
 * - establishment_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description', 50)->collation('pro9_ci_ai');
            $table->string('image', 150)->collation('pro9_ci_ai')->nullable();
            $table->boolean('active')->default(true);
            $table->bigInteger('establishment_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['establishment_id'], 'hotel_categories_establishment_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_categories');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

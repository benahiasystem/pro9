<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - address: varchar(255)
 * - location_id: json
 * - is_default: boolean
 * - is_active: boolean
 * - country_id: char(2)
 * - establishment_id: bigint
 * - establishment_code: varchar(4)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origin_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('address', 255)->collation('pro9_ci_ai');
            $table->json('location_id');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->char('country_id', 2)->collation('pro9_ci_ai')->default('VE');
            $table->bigInteger('establishment_id')->nullable();
            $table->string('establishment_code', 4)->collation('pro9_ci_ai')->default('0000');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['country_id'], 'origin_addresses_country_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('origin_addresses');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

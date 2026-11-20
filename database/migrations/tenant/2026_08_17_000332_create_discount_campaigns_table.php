<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - discount_type: varchar(255)
 * - value: numeric(30,12)
 * - starts_at: timestamp
 * - expires_at: timestamp
 * - is_active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - deleted_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('discount_type', 255)->collation('pro9_ci_ai')->default('percentage');
            $table->decimal('value', 30, 12);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['is_active', 'starts_at', 'expires_at'], 'discount_campaigns_is_active_starts_at_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_campaigns');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - delivery_zone_id: bigint
 * - department_id: char(2)
 * - province_id: char(4)
 * - district_id: char(6)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zone_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('delivery_zone_id');
            $table->char('department_id', 2)->collation('pro9_ci_ai');
            $table->char('province_id', 4)->collation('pro9_ci_ai')->nullable();
            $table->char('district_id', 6)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['delivery_zone_id'], 'delivery_zone_locations_delivery_zone_id_foreign');
            $table->index(['department_id', 'province_id', 'district_id'], 'idx_delivery_zone_ubigeo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zone_locations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

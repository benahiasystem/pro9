<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - discount_coupon_id: bigint
 * - person_id: bigint
 * - order_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_coupon_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('discount_coupon_id');
            $table->bigInteger('person_id')->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['discount_coupon_id'], 'discount_coupon_usages_discount_coupon_id_foreign');
            $table->index(['person_id'], 'discount_coupon_usages_person_id_foreign');
            $table->index(['order_id'], 'discount_coupon_usages_order_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_coupon_usages');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

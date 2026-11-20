<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - seller_id: bigint
 * - amount: numeric(30,12)
 * - commission_type: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_account_commissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->decimal('amount', 30, 12);
            $table->string('commission_type', 255)->collation('pro9_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['seller_id'], 'pending_account_commissions_seller_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_account_commissions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

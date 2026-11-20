<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - fiscal_environment: varchar(16)
 * - destination_id: bigint
 * - destination_type: varchar(255)
 * - payment_id: bigint
 * - payment_type: varchar(255)
 * - user_id: bigint
 * - cash_session_id: bigint
 * - report_context: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->bigInteger('destination_id')->nullable();
            $table->string('destination_type', 255)->collation('sab_ci_ai');
            $table->bigInteger('payment_id');
            $table->string('payment_type', 255)->collation('sab_ci_ai');
            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('cash_session_id')->nullable();
            $table->json('report_context')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['cash_session_id', 'id'], 'global_payments_cash_session_index');
            $table->index(['destination_id', 'destination_type'], 'destination_index');
            $table->index(['payment_id', 'payment_type'], 'global_payments_payment_index');
            $table->index(['fiscal_environment'], 'global_payments_fiscal_environment_foreign');
            $table->index(['user_id'], 'global_payments_user_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

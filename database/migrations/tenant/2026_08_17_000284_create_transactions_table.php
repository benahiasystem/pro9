<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - payment_link_id: bigint
 * - fiscal_environment: varchar(16)
 * - date: date
 * - time: time
 * - uuid: char(36)
 * - description: varchar(255)
 * - payment_id: varchar(255)
 * - amount: numeric(30,12)
 * - transaction_state_id: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('payment_link_id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai')->nullable();
            $table->date('date');
            $table->time('time');
            $table->char('uuid', 36)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->string('payment_id', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('amount', 30, 12);
            $table->string('transaction_state_id', 255)->collation('sab_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['uuid'], 'transactions_uuid_unique');
            $table->index(['payment_link_id'], 'transactions_payment_link_id_foreign');
            $table->index(['transaction_state_id'], 'transactions_transaction_state_id_foreign');
            $table->index(['date'], 'transactions_date_index');
            $table->index(['time'], 'transactions_time_index');
            $table->index(['description'], 'transactions_description_index');
            $table->index(['payment_id'], 'transactions_payment_id_index');
            $table->index(['amount'], 'transactions_amount_index');
            $table->index(['fiscal_environment'], 'transactions_fiscal_environment_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

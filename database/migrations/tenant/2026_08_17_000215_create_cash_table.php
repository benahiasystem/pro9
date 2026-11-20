<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - date_opening: date
 * - time_opening: time
 * - date_closed: date
 * - time_closed: time
 * - beginning_balance: numeric(30,12)
 * - final_balance: numeric(30,12)
 * - income: numeric(30,12)
 * - state: boolean
 * - reference_number: varchar(20)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - apply_restaurant: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->date('date_opening');
            $table->time('time_opening');
            $table->date('date_closed')->nullable();
            $table->time('time_closed')->nullable();
            $table->decimal('beginning_balance', 30, 12)->default('0.0000');
            $table->decimal('final_balance', 30, 12)->default('0.0000');
            $table->decimal('income', 30, 12)->default('0.0000');
            $table->boolean('state')->default(false);
            $table->string('reference_number', 20)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('apply_restaurant')->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'cash_user_id_foreign');
            $table->index(['date_opening'], 'cash_date_opening_index');
            $table->index(['income'], 'cash_income_index');
            $table->index(['state'], 'cash_state_index');
            $table->index(['reference_number'], 'cash_reference_number_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

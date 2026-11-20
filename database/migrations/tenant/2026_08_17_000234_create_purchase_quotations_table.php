<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - external_id: char(36)
 * - establishment_id: bigint
 * - establishment: json
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - prefix: varchar(255)
 * - date_of_issue: date
 * - time_of_issue: time
 * - suppliers: json
 * - filename: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_quotations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->string('prefix', 255)->collation('sab_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->json('suppliers');
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'purchase_quotations_user_id_foreign');
            $table->index(['establishment_id'], 'purchase_quotations_establishment_id_foreign');
            $table->index(['fiscal_environment'], 'purchase_quotations_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'purchase_quotations_state_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_quotations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

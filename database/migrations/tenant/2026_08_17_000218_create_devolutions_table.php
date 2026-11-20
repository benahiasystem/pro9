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
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - prefix: char(2)
 * - date_of_issue: date
 * - time_of_issue: time
 * - devolution_reason_id: bigint
 * - observation: varchar(500)
 * - filename: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolutions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->char('prefix', 2)->collation('sab_ci_ai');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->bigInteger('devolution_reason_id');
            $table->string('observation', 500)->collation('sab_ci_ai');
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'devolutions_user_id_foreign');
            $table->index(['establishment_id'], 'devolutions_establishment_id_foreign');
            $table->index(['devolution_reason_id'], 'devolutions_devolution_reason_id_foreign');
            $table->index(['fiscal_environment'], 'devolutions_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'devolutions_state_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolutions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

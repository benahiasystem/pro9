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
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - ubl_version: varchar(255)
 * - date_of_issue: date
 * - date_of_reference: date
 * - identifier: varchar(255)
 * - filename: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voided', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->string('ubl_version', 255)->collation('pro9_ci_ai');
            $table->date('date_of_issue');
            $table->date('date_of_reference');
            $table->string('identifier', 255)->collation('pro9_ci_ai');
            $table->string('filename', 255)->collation('pro9_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'voided_user_id_foreign');
            $table->index(['fiscal_environment'], 'voided_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'voided_state_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voided');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

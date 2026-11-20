<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - name: varchar(255)
 * - success: boolean
 * - status: varchar(255)
 * - status_detail: varchar(255)
 * - original_message: varchar(255)
 * - user_message: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_states', function (Blueprint $table) {
            $table->string('id', 255)->collation('pro9_ci_ai');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->boolean('success');
            $table->string('status', 255)->collation('pro9_ci_ai');
            $table->string('status_detail', 255)->collation('pro9_ci_ai');
            $table->string('original_message', 255)->collation('pro9_ci_ai');
            $table->string('user_message', 255)->collation('pro9_ci_ai');
            $table->primary(['id']);
            $table->index(['name'], 'transaction_states_name_index');
            $table->index(['success'], 'transaction_states_success_index');
            $table->index(['status'], 'transaction_states_status_index');
            $table->index(['status_detail'], 'transaction_states_status_detail_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_states');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

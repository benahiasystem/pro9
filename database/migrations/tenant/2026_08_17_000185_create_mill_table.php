<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date_start: date
 * - time_start: time
 * - date_end: date
 * - time_end: time
 * - user_id: bigint
 * - fiscal_environment: varchar(16)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - name: varchar(255)
 * - comment: text
 * - mill_name: varchar(255)
 * - lot_code: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mill', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date_start')->nullable();
            $table->time('time_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_end')->nullable();
            $table->bigInteger('user_id')->nullable()->default('0');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('name', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('comment')->collation('pro9_ci_ai')->nullable();
            $table->string('mill_name', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('lot_code', 255)->collation('pro9_ci_ai')->nullable();
            $table->index(['fiscal_environment'], 'mill_fiscal_environment_foreign');
        });
        DB::statement('ALTER TABLE "mill" ADD CONSTRAINT "mill_user_id_range" CHECK ("user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mill');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

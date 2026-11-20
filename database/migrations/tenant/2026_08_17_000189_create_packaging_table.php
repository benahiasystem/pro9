<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - user_id: bigint
 * - fiscal_environment: varchar(16)
 * - item_extra_data: json
 * - establishment_id: bigint
 * - quantity: numeric(30,12)
 * - number_packages: numeric(30,12)
 * - item: json
 * - observation: text
 * - lot_code: varchar(255)
 * - name: varchar(255)
 * - date_start: date
 * - time_start: time
 * - date_end: date
 * - time_end: time
 * - created_at: timestamp
 * - updated_at: timestamp
 * - packaging_collaborator: text
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id');
            $table->bigInteger('user_id')->nullable();
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai')->nullable();
            $table->json('item_extra_data')->nullable();
            $table->bigInteger('establishment_id')->nullable();
            $table->decimal('quantity', 30, 12)->nullable()->default('0.00');
            $table->decimal('number_packages', 30, 12)->nullable()->default('0.00');
            $table->json('item')->nullable();
            $table->text('observation')->collation('pro9_ci_ai')->nullable();
            $table->string('lot_code', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('name', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('date_start')->nullable();
            $table->time('time_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_end')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('packaging_collaborator')->collation('pro9_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['fiscal_environment'], 'packaging_fiscal_environment_foreign');
        });
        DB::statement('ALTER TABLE "packaging" ADD CONSTRAINT "packaging_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "packaging" ADD CONSTRAINT "packaging_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "packaging" ADD CONSTRAINT "packaging_establishment_id_range" CHECK ("establishment_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

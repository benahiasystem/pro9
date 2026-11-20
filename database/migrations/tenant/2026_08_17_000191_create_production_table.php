<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - fiscal_environment: varchar(16)
 * - item_id: bigint
 * - inventory_id_reference: bigint
 * - quantity: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - machine_id: bigint
 * - production_order: varchar(255)
 * - name: varchar(255)
 * - comment: text
 * - date_start: date
 * - time_start: time
 * - date_end: date
 * - time_end: time
 * - lot_code: varchar(255)
 * - item_extra_data: json
 * - mix_date_start: date
 * - mix_time_start: time
 * - mix_date_end: date
 * - mix_time_end: time
 * - informative: smallint
 * - agreed: numeric(30,12)
 * - imperfect: numeric(30,12)
 * - proccess_type: text
 * - production_collaborator: text
 * - mix_collaborator: text
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->nullable()->default('0');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('item_id')->nullable()->default('0');
            $table->bigInteger('inventory_id_reference')->nullable();
            $table->decimal('quantity', 30, 12)->nullable()->default('0.0000')->comment('Peso dle insumo ');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('machine_id')->default('0');
            $table->string('production_order', 255)->collation('sab_ci_ai')->nullable();
            $table->string('name', 255)->collation('sab_ci_ai')->nullable();
            $table->text('comment')->collation('sab_ci_ai')->nullable();
            $table->date('date_start')->nullable();
            $table->time('time_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_end')->nullable();
            $table->string('lot_code', 255)->collation('sab_ci_ai')->nullable();
            $table->json('item_extra_data')->nullable();
            $table->date('mix_date_start')->nullable();
            $table->time('mix_time_start')->nullable();
            $table->date('mix_date_end')->nullable();
            $table->time('mix_time_end')->nullable();
            $table->smallInteger('informative')->nullable()->default('0');
            $table->decimal('agreed', 30, 12)->nullable()->default('0.00');
            $table->decimal('imperfect', 30, 12)->nullable()->default('0.00');
            $table->text('proccess_type')->collation('sab_ci_ai')->nullable();
            $table->text('production_collaborator')->collation('sab_ci_ai')->nullable();
            $table->text('mix_collaborator')->collation('sab_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['fiscal_environment'], 'production_fiscal_environment_foreign');
        });
        DB::statement('ALTER TABLE "production" ADD CONSTRAINT "production_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "production" ADD CONSTRAINT "production_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "production" ADD CONSTRAINT "production_inventory_id_reference_range" CHECK ("inventory_id_reference" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "production" ADD CONSTRAINT "production_machine_id_range" CHECK ("machine_id" >= 0)');
        DB::statement('ALTER TABLE "production" ADD CONSTRAINT "production_informative_range" CHECK ("informative" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('production');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

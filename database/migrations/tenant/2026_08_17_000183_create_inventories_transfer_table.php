<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - establishment_id: bigint
 * - external_id: char(36)
 * - fiscal_environment: varchar(16)
 * - document_type_id: varchar(255)
 * - series: varchar(255)
 * - number: integer
 * - filename: varchar(255)
 * - description: varchar(255)
 * - warehouse_id: bigint
 * - warehouse_destination_id: bigint
 * - transfer_collect_id: bigint
 * - quantity: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - user_id: bigint
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories_transfer', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id');
            $table->char('external_id', 36)->collation('pro9_ci_ai')->nullable();
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai')->nullable();
            $table->string('document_type_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('series', 255)->collation('pro9_ci_ai')->nullable();
            $table->integer('number')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('description', 255)->collation('pro9_ci_ai')->nullable();
            $table->bigInteger('warehouse_id')->nullable();
            $table->bigInteger('warehouse_destination_id')->nullable();
            $table->bigInteger('transfer_collect_id')->nullable();
            $table->decimal('quantity', 30, 12);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('user_id')->nullable()->default('0')->comment('usuario que crea el registro');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['establishment_id', 'fiscal_environment', 'document_type_id', 'series', 'number'], 'inventories_transfer_branch_number_unique');
            $table->index(['establishment_id'], 'inventories_transfer_establishment_id_foreign');
            $table->index(['warehouse_id'], 'inventories_transfer_warehouse_id_foreign');
            $table->index(['warehouse_destination_id'], 'inventories_transfer_warehouse_destination_id_foreign');
            $table->index(['fiscal_environment'], 'inventories_transfer_fiscal_environment_foreign');
            $table->index(['document_type_id'], 'inventories_transfer_document_type_id_foreign');
            $table->index(['transfer_collect_id'], 'inventories_transfer_transfer_collect_id_foreign');
        });
        DB::statement('ALTER TABLE "inventories_transfer" ADD CONSTRAINT "inventories_transfer_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "inventories_transfer" ADD CONSTRAINT "inventories_transfer_warehouse_id_range" CHECK ("warehouse_id" >= 0)');
        DB::statement('ALTER TABLE "inventories_transfer" ADD CONSTRAINT "inventories_transfer_warehouse_destination_id_range" CHECK ("warehouse_destination_id" >= 0)');
        DB::statement('ALTER TABLE "inventories_transfer" ADD CONSTRAINT "inventories_transfer_user_id_range" CHECK ("user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories_transfer');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

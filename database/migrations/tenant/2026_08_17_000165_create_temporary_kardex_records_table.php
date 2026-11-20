<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - inventory_kardex_id: bigint
 * - item_name: varchar(255)
 * - date_time: timestamp
 * - date_of_issue: varchar(255)
 * - number: varchar(255)
 * - sale_note_asoc: varchar(255)
 * - order_note_asoc: varchar(255)
 * - doc_asoc: varchar(255)
 * - inventory_kardexable_type: varchar(255)
 * - item_warehouse_price: varchar(255)
 * - warehouse: varchar(255)
 * - input: varchar(255)
 * - output: varchar(255)
 * - balance: numeric(30,12)
 * - type_transaction: varchar(255)
 * - date_of_register: varchar(255)
 * - guide_id: varchar(255)
 * - transfer_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_kardex_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('inventory_kardex_id')->nullable();
            $table->string('item_name', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('date_time')->nullable();
            $table->string('date_of_issue', 255)->collation('sab_ci_ai')->nullable();
            $table->string('number', 255)->collation('sab_ci_ai')->nullable();
            $table->string('sale_note_asoc', 255)->collation('sab_ci_ai')->nullable();
            $table->string('order_note_asoc', 255)->collation('sab_ci_ai')->nullable();
            $table->string('doc_asoc', 255)->collation('sab_ci_ai')->nullable();
            $table->string('inventory_kardexable_type', 255)->collation('sab_ci_ai')->nullable();
            $table->string('item_warehouse_price', 255)->collation('sab_ci_ai')->nullable();
            $table->string('warehouse', 255)->collation('sab_ci_ai')->nullable();
            $table->string('input', 255)->collation('sab_ci_ai')->nullable();
            $table->string('output', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('balance', 30, 12)->nullable();
            $table->string('type_transaction', 255)->collation('sab_ci_ai')->nullable();
            $table->string('date_of_register', 255)->collation('sab_ci_ai')->nullable();
            $table->string('guide_id', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('transfer_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "temporary_kardex_records" ADD CONSTRAINT "temporary_kardex_records_transfer_id_range" CHECK ("transfer_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_kardex_records');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

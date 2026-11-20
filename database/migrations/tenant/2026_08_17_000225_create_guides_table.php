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
 * - user_id: bigint
 * - warehouse_id: bigint
 * - fiscal_environment: varchar(16)
 * - document_type_id: varchar(255)
 * - series: varchar(255)
 * - number: integer
 * - date_of_issue: date
 * - time_of_issue: time
 * - inventory_transaction_id: varchar(255)
 * - guideable_id: bigint
 * - guideable_type: varchar(255)
 * - observations: text
 * - filename: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('user_id');
            $table->bigInteger('warehouse_id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->string('document_type_id', 255)->collation('sab_ci_ai');
            $table->string('series', 255)->collation('sab_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->string('inventory_transaction_id', 255)->collation('sab_ci_ai');
            $table->bigInteger('guideable_id')->nullable();
            $table->string('guideable_type', 255)->collation('sab_ci_ai')->nullable();
            $table->text('observations')->collation('sab_ci_ai')->nullable();
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['establishment_id'], 'guides_establishment_id_foreign');
            $table->unique(['establishment_id', 'fiscal_environment', 'document_type_id', 'series', 'number'], 'guides_series_number_unique');
            $table->index(['user_id'], 'guides_user_id_foreign');
            $table->index(['warehouse_id'], 'guides_warehouse_id_foreign');
            $table->index(['document_type_id'], 'guides_document_type_id_foreign');
            $table->index(['inventory_transaction_id'], 'guides_inventory_transaction_id_foreign');
        });
        DB::statement('ALTER TABLE "guides" ADD CONSTRAINT "guides_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "guides" ADD CONSTRAINT "guides_guideable_id_range" CHECK ("guideable_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

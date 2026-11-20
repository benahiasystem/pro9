<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - fiscal_environment: varchar(16)
 * - uuid: char(36)
 * - user_id: bigint
 * - person_id: bigint
 * - payment_link_type_id: char(2)
 * - payment_id: bigint
 * - payment_type: varchar(255)
 * - total: numeric(30,12)
 * - status: varchar(20)
 * - paid_at: timestamp
 * - uploaded_filename: varchar(255)
 * - query_transaction: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('uuid', 36)->collation('sab_ci_ai');
            $table->bigInteger('user_id');
            $table->bigInteger('person_id')->nullable();
            $table->char('payment_link_type_id', 2)->collation('sab_ci_ai');
            $table->bigInteger('payment_id')->nullable();
            $table->string('payment_type', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('total', 30, 12);
            $table->string('status', 20)->collation('sab_ci_ai')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('uploaded_filename', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('query_transaction')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['uuid'], 'payment_links_uuid_unique');
            $table->index(['payment_id', 'payment_type'], 'payment_links_payment_index');
            $table->index(['user_id'], 'payment_links_user_id_foreign');
            $table->index(['fiscal_environment'], 'payment_links_fiscal_environment_foreign');
            $table->index(['payment_link_type_id'], 'payment_links_payment_link_type_id_foreign');
            $table->index(['person_id'], 'payment_links_person_id_foreign');
            $table->index(['status'], 'payment_links_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - provider: varchar(255)
 * - amount: numeric(30,12)
 * - currency_type_id: varchar(255)
 * - sender_name: varchar(255)
 * - raw_text: text
 * - package_name: varchar(255)
 * - posted_at: timestamp
 * - device_id: varchar(255)
 * - dedup_hash: varchar(255)
 * - status: varchar(255)
 * - matched_document_id: bigint
 * - matched_document_payment_id: bigint
 * - claimed_by_user_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 255)->collation('sab_ci_ai');
            $table->decimal('amount', 30, 12);
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->string('sender_name', 255)->collation('sab_ci_ai')->nullable();
            $table->text('raw_text')->collation('sab_ci_ai');
            $table->string('package_name', 255)->collation('sab_ci_ai');
            $table->timestamp('posted_at');
            $table->string('device_id', 255)->collation('sab_ci_ai');
            $table->string('dedup_hash', 255)->collation('C');
            $table->string('status', 255)->collation('sab_ci_ai')->default('pending');
            $table->bigInteger('matched_document_id')->nullable();
            $table->bigInteger('matched_document_payment_id')->nullable();
            $table->bigInteger('claimed_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['dedup_hash'], 'received_payments_dedup_hash_unique');
            $table->index(['provider'], 'received_payments_provider_index');
            $table->index(['device_id'], 'received_payments_device_id_index');
            $table->index(['status'], 'received_payments_status_index');
            $table->index(['matched_document_id'], 'received_payments_matched_document_id_index');
            $table->index(['matched_document_payment_id'], 'received_payments_matched_document_payment_id_index');
            $table->index(['claimed_by_user_id'], 'received_payments_claimed_by_user_id_index');
        });
        DB::statement('ALTER TABLE "received_payments" ADD CONSTRAINT "received_payments_matched_document_id_range" CHECK ("matched_document_id" >= 0)');
        DB::statement('ALTER TABLE "received_payments" ADD CONSTRAINT "received_payments_matched_document_payment_id_range" CHECK ("matched_document_payment_id" >= 0)');
        DB::statement('ALTER TABLE "received_payments" ADD CONSTRAINT "received_payments_claimed_by_user_id_range" CHECK ("claimed_by_user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('received_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

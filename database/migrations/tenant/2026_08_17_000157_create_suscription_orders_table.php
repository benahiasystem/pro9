<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - external_id: varchar(6)
 * - number: varchar(20)
 * - suscription_id: numeric(20,0)
 * - type: varchar(255)
 * - person_number: varchar(25)
 * - count_rejected_payments: integer
 * - amount: numeric(30,12)
 * - date_of_issue: timestamp
 * - date_of_due: timestamp
 * - date_of_payment: timestamp
 * - status: varchar(255)
 * - documentable_type: varchar(255)
 * - documentable_id: numeric(20,0)
 * - count_notification: integer
 * - date_notification: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscription_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('external_id', 6)->collation('C')->nullable();
            $table->string('number', 20)->collation('pro9_ci_ai')->nullable();
            $table->decimal('suscription_id', 20, 0)->nullable();
            $table->string('type', 255)->collation('pro9_ci_ai')->default('suscription_order');
            $table->string('person_number', 25)->collation('pro9_ci_ai')->nullable();
            $table->integer('count_rejected_payments')->default('0');
            $table->decimal('amount', 30, 12)->default('0.00');
            $table->timestamp('date_of_issue')->nullable();
            $table->timestamp('date_of_due')->nullable();
            $table->timestamp('date_of_payment')->nullable();
            $table->string('status', 255)->collation('pro9_ci_ai')->default('pending');
            $table->string('documentable_type', 255)->collation('pro9_ci_ai')->nullable();
            $table->decimal('documentable_id', 20, 0)->nullable();
            $table->integer('count_notification')->default('0');
            $table->timestamp('date_notification')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['external_id'], 'suscription_orders_external_id_unique');
            $table->index(['type'], 'suscription_orders_type_index');
        });
        DB::statement('ALTER TABLE "suscription_orders" ADD CONSTRAINT "suscription_orders_suscription_id_range" CHECK ("suscription_id" BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE "suscription_orders" ADD CONSTRAINT "suscription_orders_type_check" CHECK ("type" IN (\'new_suscription\',\'suscription_order\'))');
        DB::statement('ALTER TABLE "suscription_orders" ADD CONSTRAINT "suscription_orders_count_rejected_payments_range" CHECK ("count_rejected_payments" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "suscription_orders" ADD CONSTRAINT "suscription_orders_status_check" CHECK ("status" IN (\'pending\',\'paid\',\'canceled\',\'rejected\',\'expired\'))');
        DB::statement('ALTER TABLE "suscription_orders" ADD CONSTRAINT "suscription_orders_documentable_id_range" CHECK ("documentable_id" BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE "suscription_orders" ADD CONSTRAINT "suscription_orders_count_notification_range" CHECK ("count_notification" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('suscription_orders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

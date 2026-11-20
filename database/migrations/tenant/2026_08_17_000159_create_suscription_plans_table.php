<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - cat_period_id: bigint
 * - name: text
 * - description: text
 * - total: numeric(30,12)
 * - currency_type_id: varchar(255)
 * - payment_method_type_id: varchar(255)
 * - quantity_period: bigint
 * - unlimited: boolean
 * - trial_days: integer
 * - status: boolean
 * - exchange_rate_sale: numeric(18,8)
 * - total_prepayment: numeric(30,12)
 * - total_charge: numeric(30,12)
 * - total_discount: numeric(30,12)
 * - total_exportation: numeric(30,12)
 * - total_free: numeric(30,12)
 * - total_taxed: numeric(30,12)
 * - total_unaffected: numeric(30,12)
 * - total_exonerated: numeric(30,12)
 * - total_igv: numeric(30,12)
 * - total_igv_free: numeric(30,12)
 * - total_base_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - charges: json
 * - attributes: json
 * - discounts: json
 * - prepayments: json
 * - related: json
 * - perception: json
 * - legends: json
 * - terms_condition: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscription_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cat_period_id')->nullable()->default('0')->comment('Relacion con el periodo de tiempo');
            $table->text('name')->collation('sab_ci_ai')->comment('Nombre del plan');
            $table->text('description')->collation('sab_ci_ai')->comment('Descripcion del plan');
            $table->decimal('total', 30, 12)->nullable()->default('0.00')->comment('El total del costo del plan');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('payment_method_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('quantity_period')->nullable();
            $table->boolean('unlimited')->default(false);
            $table->integer('trial_days')->nullable();
            $table->boolean('status')->default(true);
            $table->decimal('exchange_rate_sale', 18, 8)->nullable()->default('0.000');
            $table->decimal('total_prepayment', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_charge', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_discount', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_exportation', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_free', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_taxed', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_unaffected', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_igv', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_igv_free', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_taxes', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_value', 30, 12)->nullable()->default('0.00');
            $table->json('charges')->nullable();
            $table->json('attributes')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('related')->nullable();
            $table->json('perception')->nullable();
            $table->json('legends')->nullable();
            $table->text('terms_condition')->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "suscription_plans" ADD CONSTRAINT "suscription_plans_cat_period_id_range" CHECK ("cat_period_id" >= 0)');
        DB::statement('ALTER TABLE "suscription_plans" ADD CONSTRAINT "suscription_plans_quantity_period_range" CHECK ("quantity_period" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "suscription_plans" ADD CONSTRAINT "suscription_plans_trial_days_range" CHECK ("trial_days" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('suscription_plans');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

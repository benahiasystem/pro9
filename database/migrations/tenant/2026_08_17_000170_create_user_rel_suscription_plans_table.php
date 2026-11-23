<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - suscription_plan_id: bigint
 * - cat_period_id: bigint
 * - items: json
 * - editable: smallint
 * - deletable: smallint
 * - start_date: date
 * - subscription_status: varchar(255)
 * - no_send_link: boolean
 * - total_send_link: integer
 * - trial_start_date: date
 * - trial_days: integer
 * - sale_notes: text
 * - documents: text
 * - dates_of_documents: text
 * - automatic_date_of_issue: date
 * - customer_id: bigint
 * - customer: json
 * - parent_customer_id: bigint
 * - parent_customer: json
 * - children_customer_id: bigint
 * - children_customer: text
 * - quantity_period: bigint
 * - apply_concurrency: bigint
 * - enabled_concurrency: bigint
 * - currency_type_id: varchar(255)
 * - payment_method_type_id: varchar(255)
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
 * - total: numeric(30,12)
 * - charges: json
 * - attributes: json
 * - discounts: json
 * - prepayments: json
 * - related: json
 * - legends: json
 * - terms_condition: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - grade: text
 * - section: text
 * - orders_created: integer
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_rel_suscription_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->nullable()->default('0')->comment('Relacion con usuario');
            $table->bigInteger('suscription_plan_id')->nullable()->default('0')->comment('Relacion con planes de suscripcion');
            $table->bigInteger('cat_period_id')->nullable()->default('0')->comment('Relacion con el periodo de tiempo');
            $table->json('items')->comment('Pega los items relacionados a modo de standar');
            $table->smallInteger('editable')->default('0')->comment('Si ya ha sido adquirido, no puede ser modificado');
            $table->smallInteger('deletable')->default('0')->comment('Si ya ha sido adquirido, no puede ser borrado');
            $table->date('start_date')->nullable()->comment('Fecha de inicio');
            $table->string('subscription_status', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('no_send_link')->default(false);
            $table->integer('total_send_link')->default('0');
            $table->date('trial_start_date')->nullable();
            $table->integer('trial_days')->nullable();
            $table->text('sale_notes')->collation('sab_ci_ai')->nullable();
            $table->text('documents')->collation('sab_ci_ai')->nullable();
            $table->text('dates_of_documents')->collation('sab_ci_ai')->nullable();
            $table->date('automatic_date_of_issue')->nullable();
            $table->bigInteger('customer_id')->nullable()->default('0');
            $table->json('customer');
            $table->bigInteger('parent_customer_id')->nullable()->default('0');
            $table->json('parent_customer');
            $table->bigInteger('children_customer_id')->nullable()->default('0');
            $table->text('children_customer')->collation('sab_ci_ai')->nullable();
            $table->bigInteger('quantity_period')->nullable()->default('0');
            $table->bigInteger('apply_concurrency')->default('0');
            $table->bigInteger('enabled_concurrency')->default('1')->comment('Se pasará a nota de ventas como activo');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('payment_method_type_id', 255)->collation('sab_ci_ai')->nullable();
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
            $table->decimal('total', 30, 12)->nullable()->default('0.00');
            $table->json('charges')->nullable();
            $table->json('attributes')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('related')->nullable();
            $table->json('legends')->nullable();
            $table->text('terms_condition')->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('grade')->collation('sab_ci_ai')->nullable()->comment('Grado designado - utilizado en matricula');
            $table->text('section')->collation('sab_ci_ai')->nullable()->comment('Seccion designado - utilizado en matricula');
            $table->integer('orders_created')->default('0')->comment('Cantidad de órdenes de cobro creadas para esta suscripción');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_suscription_plan_id_range" CHECK ("suscription_plan_id" >= 0)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_cat_period_id_range" CHECK ("cat_period_id" >= 0)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_editable_range" CHECK ("editable" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_deletable_range" CHECK ("deletable" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_subscription_status_check" CHECK ("subscription_status" IN (\'authorized\',\'paused\',\'cancelled\',\'finished\'))');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_total_send_link_range" CHECK ("total_send_link" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_trial_days_range" CHECK ("trial_days" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_customer_id_range" CHECK ("customer_id" >= 0)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_parent_customer_id_range" CHECK ("parent_customer_id" >= 0)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_children_customer_id_range" CHECK ("children_customer_id" >= 0)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_quantity_period_range" CHECK ("quantity_period" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_apply_concurrency_range" CHECK ("apply_concurrency" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_enabled_concurrency_range" CHECK ("enabled_concurrency" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "user_rel_suscription_plans" ADD CONSTRAINT "user_rel_suscription_plans_orders_created_range" CHECK ("orders_created" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rel_suscription_plans');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

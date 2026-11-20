<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - external_id: char(36)
 * - establishment_id: bigint
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - group_id: char(2)
 * - document_type_id: varchar(255)
 * - series: char(4)
 * - number: integer
 * - date_of_issue: date
 * - date_of_due: date
 * - time_of_issue: time
 * - supplier_id: bigint
 * - supplier: json
 * - currency_type_id: varchar(255)
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
 * - total_base_other_taxes: numeric(30,12)
 * - total_other_taxes: numeric(30,12)
 * - total_taxes: numeric(30,12)
 * - total_value: numeric(30,12)
 * - total: numeric(30,12)
 * - customer_id: bigint
 * - perception_date: date
 * - perception_number: integer
 * - total_perception: numeric(30,12)
 * - charges: json
 * - discounts: json
 * - prepayments: json
 * - guides: json
 * - related: json
 * - perception: json
 * - legends: json
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_purchases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->char('group_id', 2)->collation('sab_ci_ai');
            $table->string('document_type_id', 255)->collation('sab_ci_ai');
            $table->char('series', 4)->collation('sab_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->date('date_of_due')->nullable();
            $table->time('time_of_issue');
            $table->bigInteger('supplier_id');
            $table->json('supplier');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('exchange_rate_sale', 18, 8);
            $table->decimal('total_prepayment', 30, 12)->default('0.00');
            $table->decimal('total_charge', 30, 12)->default('0.00');
            $table->decimal('total_discount', 30, 12)->default('0.00');
            $table->decimal('total_exportation', 30, 12)->default('0.00');
            $table->decimal('total_free', 30, 12)->default('0.00');
            $table->decimal('total_taxed', 30, 12)->default('0.00');
            $table->decimal('total_unaffected', 30, 12)->default('0.00');
            $table->decimal('total_exonerated', 30, 12)->default('0.00');
            $table->decimal('total_igv', 30, 12)->default('0.00');
            $table->decimal('total_base_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_other_taxes', 30, 12)->default('0.00');
            $table->decimal('total_taxes', 30, 12)->default('0.00');
            $table->decimal('total_value', 30, 12)->default('0.00');
            $table->decimal('total', 30, 12);
            $table->bigInteger('customer_id')->nullable();
            $table->date('perception_date')->nullable();
            $table->integer('perception_number')->nullable();
            $table->decimal('total_perception', 30, 12)->nullable();
            $table->json('charges')->nullable();
            $table->json('discounts')->nullable();
            $table->json('prepayments')->nullable();
            $table->json('guides')->nullable();
            $table->json('related')->nullable();
            $table->json('perception')->nullable();
            $table->json('legends')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'fixed_asset_purchases_user_id_foreign');
            $table->index(['establishment_id'], 'fixed_asset_purchases_establishment_id_foreign');
            $table->index(['supplier_id'], 'fixed_asset_purchases_supplier_id_foreign');
            $table->index(['customer_id'], 'fixed_asset_purchases_customer_id_foreign');
            $table->index(['fiscal_environment'], 'fixed_asset_purchases_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'fixed_asset_purchases_state_type_id_foreign');
            $table->index(['group_id'], 'fixed_asset_purchases_group_id_foreign');
            $table->index(['document_type_id'], 'fixed_asset_purchases_document_type_id_foreign');
            $table->index(['currency_type_id'], 'fixed_asset_purchases_currency_type_id_foreign');
        });
        DB::statement('ALTER TABLE "fixed_asset_purchases" ADD CONSTRAINT "fixed_asset_purchases_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "fixed_asset_purchases" ADD CONSTRAINT "fixed_asset_purchases_perception_number_range" CHECK ("perception_number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_purchases');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

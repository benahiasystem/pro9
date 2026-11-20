<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - code: varchar(20)
 * - public_code: varchar(15)
 * - tracking_number: integer
 * - parent_code: varchar(15)
 * - identity_document_type: varchar(10)
 * - identity_document_number: varchar(20)
 * - name: varchar(200)
 * - district_id: char(6)
 * - address: varchar(300)
 * - email: varchar(150)
 * - phone: varchar(20)
 * - asset_type: varchar(15)
 * - asset_description: varchar(500)
 * - asset_date: date
 * - has_receipt: boolean
 * - receipt_series: varchar(10)
 * - receipt_number: varchar(20)
 * - receipt_amount: numeric(30,12)
 * - receipt_currency: varchar(3)
 * - claim_type: varchar(10)
 * - detail: text
 * - expected_result: text
 * - channel: varchar(100)
 * - attachments: json
 * - pdf_path: varchar(500)
 * - terms_accepted: boolean
 * - status_claim_id: bigint
 * - assigned_user_id: bigint
 * - resolution: text
 * - response_attachments: json
 * - is_closed: boolean
 * - closed_at: timestamp
 * - due_date: date
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->collation('pro9_ci_ai');
            $table->string('public_code', 15)->collation('pro9_ci_ai');
            $table->integer('tracking_number')->default('0');
            $table->string('parent_code', 15)->collation('pro9_ci_ai')->nullable();
            $table->string('identity_document_type', 10)->collation('pro9_ci_ai');
            $table->string('identity_document_number', 20)->collation('pro9_ci_ai');
            $table->string('name', 200)->collation('pro9_ci_ai');
            $table->char('district_id', 6)->collation('pro9_ci_ai')->nullable();
            $table->string('address', 300)->collation('pro9_ci_ai')->nullable();
            $table->string('email', 150)->collation('pro9_ci_ai');
            $table->string('phone', 20)->collation('pro9_ci_ai')->nullable();
            $table->string('asset_type', 15)->collation('pro9_ci_ai');
            $table->string('asset_description', 500)->collation('pro9_ci_ai');
            $table->date('asset_date');
            $table->boolean('has_receipt')->default(false);
            $table->string('receipt_series', 10)->collation('pro9_ci_ai')->nullable();
            $table->string('receipt_number', 20)->collation('pro9_ci_ai')->nullable();
            $table->decimal('receipt_amount', 30, 12)->nullable();
            $table->string('receipt_currency', 3)->collation('pro9_ci_ai')->nullable();
            $table->string('claim_type', 10)->collation('pro9_ci_ai');
            $table->text('detail')->collation('pro9_ci_ai');
            $table->text('expected_result')->collation('pro9_ci_ai');
            $table->string('channel', 100)->collation('pro9_ci_ai')->nullable();
            $table->json('attachments')->nullable();
            $table->string('pdf_path', 500)->collation('pro9_ci_ai')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->bigInteger('status_claim_id')->nullable();
            $table->bigInteger('assigned_user_id')->nullable();
            $table->text('resolution')->collation('pro9_ci_ai')->nullable();
            $table->json('response_attachments')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['code'], 'claims_code_unique');
            $table->unique(['public_code'], 'claims_public_code_unique');
            $table->index(['status_claim_id'], 'claims_status_claim_id_foreign');
            $table->index(['district_id'], 'claims_district_id_foreign');
            $table->index(['parent_code'], 'claims_parent_code_index');
            $table->index(['assigned_user_id'], 'claims_assigned_user_id_foreign');
        });
        DB::statement('ALTER TABLE "claims" ADD CONSTRAINT "claims_tracking_number_range" CHECK ("tracking_number" BETWEEN 0 AND 65535)');
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

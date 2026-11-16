<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_fiscal_years', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
            $table->uuid('result_entry_id')->nullable();
        });
        Schema::create('accounting_year_closings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('company_id');
            $table->uuid('fiscal_year_id');
            $table->uuid('active_key')->nullable();
            $table->uuid('result_account_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('building');
            $table->unsignedInteger('prepared_by');
            $table->unsignedInteger('posted_by')->nullable();
            $table->unsignedInteger('withdrawn_by')->nullable();
            $table->unsignedInteger('reopening_prepared_by')->nullable();
            $table->unsignedInteger('reopened_by')->nullable();
            $table->text('reason');
            $table->text('withdrawal_reason')->nullable();
            $table->text('reopening_reason')->nullable();
            $table->json('attestations');
            $table->json('exceptions');
            $table->json('basis');
            $table->string('basis_hash', 64);
            $table->json('proposal');
            $table->json('entries');
            $table->json('posted_snapshot')->nullable();
            $table->json('reopening_entries')->nullable();
            $table->json('reopened_snapshot')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->dateTime('reopened_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'company_id', 'id'], 'accounting_year_closing_owner');
            $table->unique(['company_id', 'active_key'], 'accounting_year_closing_active');
            $table->index(['company_id', 'fiscal_year_id', 'created_at'], 'accounting_year_closing_history');
            $table->foreign(['organization_id', 'company_id'])->references(['organization_id', 'id'])->on('accounting_companies')->restrictOnDelete();
            $table->foreign(['organization_id', 'company_id', 'fiscal_year_id'], 'accounting_year_closing_year')->references(['organization_id', 'company_id', 'id'])->on('accounting_fiscal_years')->restrictOnDelete();
            $table->foreign(['organization_id', 'company_id', 'result_account_id'], 'accounting_year_closing_account')->references(['organization_id', 'company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
            foreach (['prepared_by', 'posted_by', 'withdrawn_by', 'reopening_prepared_by', 'reopened_by'] as $column) {
                $table->foreign($column)->references('id')->on('users')->restrictOnDelete();
            }
        });
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER accounting_year_closing_upd BEFORE UPDATE ON accounting_year_closings FOR EACH ROW BEGIN
                IF NOT (OLD.fiscal_year_id <=> NEW.fiscal_year_id) OR NOT (OLD.result_account_id <=> NEW.result_account_id)
                   OR NOT (OLD.prepared_by <=> NEW.prepared_by) OR NOT (OLD.reason <=> NEW.reason)
                   OR NOT (OLD.basis <=> NEW.basis) OR NOT (OLD.basis_hash <=> NEW.basis_hash)
                   OR NOT (OLD.proposal <=> NEW.proposal) OR NOT (OLD.attestations <=> NEW.attestations) OR NOT (OLD.exceptions <=> NEW.exceptions)
                   OR (OLD.status <> 'building' AND NOT (OLD.entries <=> NEW.entries))
                   OR (OLD.posted_snapshot IS NOT NULL AND NOT (OLD.posted_snapshot <=> NEW.posted_snapshot))
                   OR (OLD.reopening_entries IS NOT NULL AND NOT (OLD.reopening_entries <=> NEW.reopening_entries))
                   OR (OLD.reopening_reason IS NOT NULL AND NOT (OLD.reopening_reason <=> NEW.reopening_reason))
                   OR (OLD.withdrawal_reason IS NOT NULL AND NOT (OLD.withdrawal_reason <=> NEW.withdrawal_reason))
                   OR (OLD.reopened_snapshot IS NOT NULL AND NOT (OLD.reopened_snapshot <=> NEW.reopened_snapshot)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Evidencia de cierre anual inmutable';
                END IF;
                IF OLD.status <> NEW.status AND NOT (
                    (OLD.status='building' AND NEW.status='prepared') OR
                    (OLD.status='prepared' AND NEW.status IN ('finalizing','cancelled')) OR
                    (OLD.status='finalizing' AND NEW.status='posted') OR
                    (OLD.status='posted' AND NEW.status='reopening') OR
                    (OLD.status='reopening' AND NEW.status='reopen_finalizing') OR
                    (OLD.status='reopen_finalizing' AND NEW.status='reopened')
                ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transicion anual no permitida'; END IF;
            END");
            DB::unprepared("CREATE TRIGGER accounting_year_closing_del BEFORE DELETE ON accounting_year_closings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historial anual conservado'");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Los comprobantes y la evidencia del cierre anual se conservan.');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;
return new class extends Migration
{
    private function owned(string $name, Closure $fields): void
    {
        Schema::create($name, function (Blueprint $t) use ($name, $fields) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->uuid('company_id');
            $t->timestamps();
            $t->unique(['organization_id', 'company_id', 'id'], 'uq_' . $name . '_scope');
            $t->foreign(['organization_id', 'company_id'], 'fk_' . $name . '_company')->references(['organization_id', 'id'])->on('accounting_companies')->restrictOnDelete();
            $fields($t);
        });
    }
    private function link(string $table, string $column, string $target): void
    {
        Schema::table($table, function (Blueprint $t) use ($table, $column, $target) {
            $t->foreign(['organization_id', 'company_id', $column], 'fk_' . $table . '_' . $column)->references(['organization_id', 'company_id', 'id'])->on($target)->restrictOnDelete();
        });
    }
    protected $connection = 'tenant';
    public function up(): void
    {
        Schema::create('accounting_organizations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('accounting_companies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained('accounting_organizations')->restrictOnDelete();
            $t->string('name');
            $t->string('tax_id', 24);
            $t->string('activity')->nullable();
            $t->string('address')->nullable();
            $t->string('state')->nullable();
            $t->string('municipality')->nullable();
            $t->string('base_currency', 3)->default('VES');
            $t->string('tax_profile', 24)->default('ordinary');
            $t->string('approval_mode', 24)->default('separate');
            $t->unsignedInteger('offline_hours')->default(72);
            $t->unsignedInteger('workspace_days')->default(90);
            $t->boolean('active')->default(true);
            $t->date('migration_date')->nullable();
            $t->json('settings')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'id']);
            $t->unique(['organization_id', 'tax_id']);
        });
        $this->owned('accounting_memberships', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $t->string('role', 24);
            $t->boolean('active')->default(true);
            $t->unique(['company_id', 'user_id']);
        });
        $this->owned('accounting_establishments', function (Blueprint $t) {
            $t->string('name');
            $t->string('code', 30);
            $t->string('address')->nullable();
            $t->boolean('active')->default(true);
            $t->unique(['company_id', 'code']);
        });
        $this->owned('accounting_fiscal_years', function (Blueprint $t) {
            $t->unsignedSmallInteger('year');
            $t->string('status', 24)->default('open');
            $t->uuid('closing_entry_id')->nullable();
            $t->uuid('opening_entry_id')->nullable();
            $t->unique(['company_id', 'year']);
        });
        $this->owned('accounting_periods', function (Blueprint $t) {
            $t->uuid('fiscal_year_id');
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');
            $t->string('status', 24)->default('open');
            $t->unsignedInteger('version')->default(1);
            $t->json('close_snapshot')->nullable();
            $t->unique(['company_id', 'year', 'month']);
        });
        $this->link('accounting_periods', 'fiscal_year_id', 'accounting_fiscal_years');
        $this->owned('accounting_accounts', function (Blueprint $t) {
            $t->string('code', 40);
            $t->string('name');
            $t->uuid('parent_id')->nullable();
            $t->string('kind', 24);
            $t->string('nature', 8);
            $t->boolean('posting')->default(true);
            $t->boolean('active')->default(true);
            $t->boolean('requires_party')->default(false);
            $t->string('auxiliary', 24)->nullable();
            $t->string('report_group', 80)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->unique(['company_id', 'code']);
        });
        $this->link('accounting_accounts', 'parent_id', 'accounting_accounts');
        $this->owned('accounting_third_parties', function (Blueprint $t) {
            $t->string('tax_id', 30);
            $t->string('name');
            $t->string('kind', 20)->default('other');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('address')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->unique(['company_id', 'tax_id']);
        });
        $this->owned('accounting_cost_centers', function (Blueprint $t) {
            $t->string('code', 30);
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->unique(['company_id', 'code']);
        });
        $this->owned('accounting_exchange_rates', function (Blueprint $t) {
            $t->string('currency', 3);
            $t->date('effective_date');
            $t->decimal('value', 28, 12);
            $t->string('source');
            $t->text('reason');
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('created_by');
            $t->foreign('created_by')->references('id')->on('users');
        });
        $this->owned('accounting_rule_sets', function (Blueprint $t) {
            $t->string('code', 60);
            $t->string('name');
            $t->string('tax', 24);
            $t->string('scenario', 100);
            $t->decimal('rate_percent', 18, 8)->nullable();
            $t->date('published_on')->nullable();
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->string('source_reference');
            $t->string('source_url', 1000)->nullable();
            $t->string('status', 24)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->text('notes')->nullable();
            $t->unsignedInteger('approved_by')->nullable();
            $t->foreign('approved_by')->references('id')->on('users');
            $t->dateTime('approved_at')->nullable();
            $t->unique(['company_id', 'code', 'version']);
        });
        $this->owned('accounting_tax_profiles', function (Blueprint $t) {
            $t->string('profile', 24);
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->json('details');
            $t->string('source_reference');
            $t->unsignedInteger('approved_by');
            $t->foreign('approved_by')->references('id')->on('users');
        });
        $this->owned('accounting_source_documents', function (Blueprint $t) {
            $t->string('origin', 24)->default('manual');
            $t->string('source_instance_id', 60)->nullable();
            $t->string('source_company_id', 100)->nullable();
            $t->string('source_id', 100)->nullable();
            $t->string('kind', 40);
            $t->string('number', 100);
            $t->string('fiscal_control', 100)->nullable();
            $t->date('issue_date');
            $t->date('fiscal_date');
            $t->date('due_date')->nullable();
            $t->uuid('third_party_id')->nullable();
            $t->uuid('related_document_id')->nullable();
            $t->string('currency', 3);
            $t->decimal('exchange_rate', 28, 12)->default(1);
            $t->string('exchange_source');
            $t->date('exchange_date');
            $t->json('amounts');
            $t->json('fiscal_amounts');
            $t->json('tax_components')->nullable();
            $t->string('status', 24)->default('draft');
            $t->unsignedInteger('source_revision')->default(1);
            $t->unsignedInteger('version')->default(1);
            $t->json('issues')->nullable();
            $t->json('metadata')->nullable();
            $t->unique(['company_id', 'source_instance_id', 'source_company_id', 'source_id'], 'uq_source_identity');
        });
        $this->link('accounting_source_documents', 'third_party_id', 'accounting_third_parties');
        $this->link('accounting_source_documents', 'related_document_id', 'accounting_source_documents');
        $this->owned('accounting_source_versions', function (Blueprint $t) {
            $t->uuid('source_document_id');
            $t->unsignedInteger('revision');
            $t->json('snapshot');
            $t->string('hash', 64);
            $t->unique(['company_id', 'source_document_id', 'revision'], 'uq_source_revision');
        });
        $this->link('accounting_source_versions', 'source_document_id', 'accounting_source_documents');
        $this->owned('accounting_journal_sequences', function (Blueprint $t) {
            $t->unsignedSmallInteger('year');
            $t->string('series', 12)->default('CG');
            $t->unsignedBigInteger('last_number')->default(0);
            $t->unique(['company_id', 'year', 'series']);
        });
        $this->owned('accounting_journal_entries', function (Blueprint $t) {
            $t->uuid('period_id');
            $t->date('accounting_date');
            $t->string('concept', 500);
            $t->string('entry_type', 24)->default('regular');
            $t->string('status', 24)->default('draft');
            $t->string('reference', 40)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('created_by');
            $t->foreign('created_by')->references('id')->on('users');
            $t->unsignedInteger('approved_by')->nullable();
            $t->foreign('approved_by')->references('id')->on('users');
            $t->dateTime('approved_at')->nullable();
            $t->unsignedInteger('approved_version')->nullable();
            $t->text('approval_reason')->nullable();
            $t->dateTime('posted_at')->nullable();
            $t->unsignedInteger('posted_by')->nullable();
            $t->foreign('posted_by')->references('id')->on('users');
            $t->uuid('source_document_id')->nullable();
            $t->unsignedInteger('source_revision')->nullable();
            $t->string('effect_type', 32)->default('document');
            $t->uuid('reversal_of')->nullable();
            $t->text('reason')->nullable();
            $t->string('local_id', 36)->nullable();
            $t->json('rule_snapshot')->nullable();
            $t->json('metadata')->nullable();
            $t->decimal('total_debit', 28, 8)->default(0);
            $t->decimal('total_credit', 28, 8)->default(0);
            $t->unique(['company_id', 'reference']);
            $t->unique(['company_id', 'reversal_of']);
            $t->unique(['company_id', 'created_by', 'local_id'], 'uq_local_draft');
            $t->unique(['company_id', 'source_document_id', 'source_revision', 'effect_type'], 'uq_source_effect');
            $t->index(['company_id', 'accounting_date', 'posted_at'], 'accounting_entries_date_posted');
        });
        $this->link('accounting_journal_entries', 'period_id', 'accounting_periods');
        $this->link('accounting_journal_entries', 'source_document_id', 'accounting_source_documents');
        $this->link('accounting_journal_entries', 'reversal_of', 'accounting_journal_entries');
        $this->owned('accounting_journal_lines', function (Blueprint $t) {
            $t->uuid('journal_entry_id');
            $t->uuid('account_id');
            $t->uuid('third_party_id')->nullable();
            $t->uuid('cost_center_id')->nullable();
            $t->unsignedInteger('position');
            $t->string('description', 500)->nullable();
            $t->string('document_reference', 100)->nullable();
            $t->decimal('debit', 28, 8);
            $t->decimal('credit', 28, 8);
            $t->string('original_currency', 3)->default('VES');
            $t->decimal('original_amount', 28, 8)->nullable();
            $t->decimal('exchange_rate', 28, 12)->nullable();
        });
        $this->link('accounting_journal_lines', 'journal_entry_id', 'accounting_journal_entries');
        $this->link('accounting_journal_lines', 'account_id', 'accounting_accounts');
        $this->link('accounting_journal_lines', 'third_party_id', 'accounting_third_parties');
        $this->link('accounting_journal_lines', 'cost_center_id', 'accounting_cost_centers');
        $this->owned('accounting_entry_reviews', function (Blueprint $t) {
            $t->uuid('journal_entry_id');
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->unsignedInteger('entry_version');
            $t->string('action', 24);
            $t->text('comment');
        });
        $this->link('accounting_entry_reviews', 'journal_entry_id', 'accounting_journal_entries');
        $this->owned('accounting_open_items', function (Blueprint $t) {
            $t->uuid('third_party_id');
            $t->uuid('account_id');
            $t->uuid('source_document_id')->nullable();
            $t->uuid('opening_entry_id')->nullable();
            $t->string('side', 12);
            $t->string('reference', 100);
            $t->date('issue_date');
            $t->date('due_date')->nullable();
            $t->string('currency', 3);
            $t->decimal('original_amount', 28, 8);
            $t->decimal('book_amount', 28, 8);
            $t->decimal('remaining_original', 28, 8);
            $t->decimal('remaining_book', 28, 8);
            $t->string('status', 24)->default('open');
            $t->boolean('is_opening')->default(false);
        });
        foreach (['third_party_id' => 'accounting_third_parties', 'account_id' => 'accounting_accounts', 'source_document_id' => 'accounting_source_documents', 'opening_entry_id' => 'accounting_journal_entries'] as $k => $v) {
            $this->link('accounting_open_items', $k, $v);
        }
        $this->owned('accounting_settlement_allocations', function (Blueprint $t) {
            $t->uuid('open_item_id');
            $t->uuid('journal_entry_id');
            $t->decimal('original_amount', 28, 8);
            $t->decimal('book_amount', 28, 8);
            $t->decimal('settlement_amount', 28, 8);
            $t->decimal('exchange_difference', 28, 8);
            $t->date('settlement_date');
            $t->uuid('reversal_entry_id')->nullable();
            $t->string('source_key', 150)->nullable();
            $t->unique(['company_id', 'source_key']);
        });
        $this->link('accounting_settlement_allocations', 'open_item_id', 'accounting_open_items');
        $this->link('accounting_settlement_allocations', 'journal_entry_id', 'accounting_journal_entries');
        $this->link('accounting_settlement_allocations', 'reversal_entry_id', 'accounting_journal_entries');
        $this->owned('accounting_fiscal_records', function (Blueprint $t) {
            $t->uuid('source_document_id')->nullable();
            $t->string('kind', 40);
            $t->date('event_date');
            $t->string('fiscal_period', 7);
            $t->string('profile', 24);
            $t->json('amounts_ves');
            $t->json('tax_components')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 24)->default('validated');
            $t->json('rule_snapshot')->nullable();
            $t->text('notes')->nullable();
        });
        $this->link('accounting_fiscal_records', 'source_document_id', 'accounting_source_documents');
        $this->owned('accounting_retentions', function (Blueprint $t) {
            $t->uuid('source_document_id');
            $t->uuid('third_party_id');
            $t->string('kind', 12);
            $t->string('direction', 12);
            $t->string('number', 100);
            $t->date('event_date');
            $t->decimal('base_amount', 28, 8);
            $t->decimal('rate_percent', 18, 8);
            $t->decimal('amount', 28, 8);
            $t->string('currency', 3)->default('VES');
            $t->decimal('exchange_rate', 28, 12)->default(1);
            $t->string('status', 24)->default('planned');
            $t->text('notes')->nullable();
            $t->uuid('reversal_of')->nullable();
        });
        $this->link('accounting_retentions', 'source_document_id', 'accounting_source_documents');
        $this->link('accounting_retentions', 'third_party_id', 'accounting_third_parties');
        $this->owned('accounting_integration_connections', function (Blueprint $t) {
            $t->string('name');
            $t->string('source_instance_id', 60);
            $t->string('source_company_id', 100);
            $t->string('token_hash', 64);
            $t->text('signing_secret');
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('checkpoint')->default(0);
            $t->string('stream_id', 36)->nullable();
            $t->dateTime('last_received_at')->nullable();
            $t->unique(['source_instance_id', 'source_company_id'], 'uq_connection_source');
        });
        $this->owned('accounting_integration_batches', function (Blueprint $t) {
            $t->uuid('connection_id');
            $t->string('batch_id', 36);
            $t->string('body_hash', 64);
            $t->json('payload');
            $t->string('status', 24)->default('received');
            $t->json('result')->nullable();
            $t->dateTime('received_at');
            $t->unique(['connection_id', 'batch_id']);
        });
        $this->link('accounting_integration_batches', 'connection_id', 'accounting_integration_connections');
        $this->owned('accounting_inbox_events', function (Blueprint $t) {
            $t->uuid('connection_id');
            $t->uuid('batch_record_id');
            $t->string('event_id', 36);
            $t->string('stream_id', 36);
            $t->unsignedBigInteger('stream_sequence');
            $t->string('aggregate_type', 30);
            $t->string('source_id', 100);
            $t->unsignedInteger('source_revision');
            $t->string('event_type', 50);
            $t->json('payload');
            $t->string('hash', 64);
            $t->string('status', 24)->default('received');
            $t->json('issues')->nullable();
            $t->unique(['connection_id', 'event_id']);
            $t->unique(['connection_id', 'stream_id', 'stream_sequence'], 'uq_stream_sequence');
        });
        $this->link('accounting_inbox_events', 'connection_id', 'accounting_integration_connections');
        $this->link('accounting_inbox_events', 'batch_record_id', 'accounting_integration_batches');
        $this->owned('accounting_integration_nonces', function (Blueprint $t) {
            $t->uuid('connection_id');
            $t->string('nonce', 100);
            $t->dateTime('expires_at');
            $t->unique(['connection_id', 'nonce']);
        });
        $this->link('accounting_integration_nonces', 'connection_id', 'accounting_integration_connections');
        $this->owned('accounting_account_mappings', function (Blueprint $t) {
            $t->string('key', 100);
            $t->uuid('account_id');
            $t->unique(['company_id', 'key']);
        });
        $this->link('accounting_account_mappings', 'account_id', 'accounting_accounts');
        $this->owned('accounting_incidents', function (Blueprint $t) {
            $t->string('kind', 40);
            $t->string('resource_type', 40);
            $t->uuid('resource_id')->nullable();
            $t->string('title');
            $t->json('details');
            $t->string('status', 24)->default('open');
            $t->text('resolution')->nullable();
            $t->unsignedInteger('resolved_by')->nullable();
            $t->foreign('resolved_by')->references('id')->on('users');
        });
        $this->owned('accounting_attachments', function (Blueprint $t) {
            $t->string('resource_type', 40);
            $t->uuid('resource_id');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->string('scan_status', 24)->default('pending');
            $t->unsignedInteger('uploaded_by');
            $t->foreign('uploaded_by')->references('id')->on('users');
        });
        $this->owned('accounting_operation_results', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->string('action', 50);
            $t->string('operation_key', 100);
            $t->string('content_hash', 64);
            $t->json('result')->nullable();
            $t->unique(['company_id', 'user_id', 'action', 'operation_key'], 'uq_operation_key');
        });
        $this->owned('accounting_audit_heads', function (Blueprint $t) {
            $t->unsignedBigInteger('last_sequence')->default(0);
            $t->string('last_hash', 64)->default('');
            $t->unique('company_id');
        });
        $this->owned('accounting_audit_events', function (Blueprint $t) {
            $t->unsignedBigInteger('sequence');
            $t->unsignedInteger('user_id')->nullable();
            $t->foreign('user_id')->references('id')->on('users');
            $t->string('action', 100);
            $t->string('resource_type', 50);
            $t->string('resource_id', 100)->nullable();
            $t->json('payload');
            $t->string('previous_hash', 64);
            $t->string('hash', 64);
            $t->unique(['company_id', 'sequence']);
        });
        $this->owned('accounting_devices', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->string('device_id', 36);
            $t->string('name', 100);
            $t->dateTime('authorized_until')->nullable();
            $t->dateTime('last_seen_at')->nullable();
            $t->dateTime('revoked_at')->nullable();
            $t->unique(['company_id', 'user_id', 'device_id']);
        });
        $this->owned('accounting_import_jobs', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->string('kind', 30);
            $t->string('file_hash', 64);
            $t->string('path');
            $t->string('status', 24)->default('queued');
            $t->json('result')->nullable();
            $t->json('errors')->nullable();
        });
        $this->owned('accounting_report_jobs', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->string('kind', 40);
            $t->string('format', 8);
            $t->json('filters');
            $t->string('status', 24)->default('queued');
            $t->string('path')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->json('snapshot')->nullable();
            $t->text('error')->nullable();
        });
        $this->owned('accounting_obligations', function (Blueprint $t) {
            $t->string('name');
            $t->string('kind', 40);
            $t->date('due_date')->nullable();
            $t->string('source_reference');
            $t->string('status', 24)->default('external_determination');
            $t->decimal('amount', 28, 8)->nullable();
            $t->text('notes')->nullable();
        });
        $this->owned('accounting_period_reviews', function (Blueprint $t) {
            $t->uuid('period_id');
            $t->unsignedInteger('user_id');
            $t->foreign('user_id')->references('id')->on('users');
            $t->string('action', 24);
            $t->text('reason');
            $t->json('snapshot');
        });
        $this->link('accounting_period_reviews', 'period_id', 'accounting_periods');
        if (DB::getDriverName() === 'mysql') {
            foreach (['accounting_audit_events', 'accounting_source_versions'] as $table) {
                DB::unprepared("CREATE TRIGGER imm_" . $table . "_upd BEFORE UPDATE ON " . $table . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro de evidencia inmutable'");
                DB::unprepared("CREATE TRIGGER imm_" . $table . "_del BEFORE DELETE ON " . $table . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro de evidencia inmutable'");
            }
            DB::unprepared("CREATE TRIGGER accounting_posted_entry_upd BEFORE UPDATE ON accounting_journal_entries FOR EACH ROW BEGIN IF OLD.posted_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comprobante contabilizado inmutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_posted_entry_del BEFORE DELETE ON accounting_journal_entries FOR EACH ROW BEGIN IF OLD.posted_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comprobante contabilizado inmutable'; END IF; END");
            foreach (['UPDATE' => 'upd', 'DELETE' => 'del'] as $event => $suffix) {
                $targetCheck = $event === 'UPDATE' ? " OR (SELECT posted_at FROM accounting_journal_entries WHERE id=NEW.journal_entry_id) IS NOT NULL" : '';
                DB::unprepared("CREATE TRIGGER accounting_posted_lines_" . $suffix . " BEFORE " . $event . " ON accounting_journal_lines FOR EACH ROW BEGIN IF (SELECT posted_at FROM accounting_journal_entries WHERE id=OLD.journal_entry_id) IS NOT NULL" . $targetCheck . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Líneas contabilizadas inmutables'; END IF; END");
            }
            DB::unprepared("CREATE TRIGGER accounting_posted_lines_ins BEFORE INSERT ON accounting_journal_lines FOR EACH ROW BEGIN IF (SELECT posted_at FROM accounting_journal_entries WHERE id=NEW.journal_entry_id) IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Líneas contabilizadas inmutables'; END IF; END");
        }
    }
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['accounting_period_reviews', 'accounting_obligations', 'accounting_report_jobs', 'accounting_import_jobs', 'accounting_devices', 'accounting_audit_events', 'accounting_audit_heads', 'accounting_operation_results', 'accounting_attachments', 'accounting_incidents', 'accounting_account_mappings', 'accounting_integration_nonces', 'accounting_inbox_events', 'accounting_integration_batches', 'accounting_integration_connections', 'accounting_retentions', 'accounting_fiscal_records', 'accounting_settlement_allocations', 'accounting_open_items', 'accounting_entry_reviews', 'accounting_journal_lines', 'accounting_journal_entries', 'accounting_journal_sequences', 'accounting_source_versions', 'accounting_source_documents', 'accounting_tax_profiles', 'accounting_rule_sets', 'accounting_exchange_rates', 'accounting_cost_centers', 'accounting_third_parties', 'accounting_accounts', 'accounting_periods', 'accounting_fiscal_years', 'accounting_establishments', 'accounting_memberships', 'accounting_companies', 'accounting_organizations'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};

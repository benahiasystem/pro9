<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;
return new class extends Migration
{
    protected $connection = 'tenant';
    private function owned(string $table, Closure $fields): void
    {
        Schema::create($table, function (Blueprint $t) use ($table, $fields) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->uuid('company_id');
            $t->timestamps();
            $t->unique(['organization_id', 'company_id', 'id'], 'uq_' . $table . '_scope');
            $t->foreign(['organization_id', 'company_id'], 'fk_' . $table . '_company')->references(['organization_id', 'id'])->on('accounting_companies')->restrictOnDelete();
            $fields($t);
            $t->foreign(['organization_id', 'company_id', 'journal_entry_id'], 'fk_' . $table . '_entry')->references(['organization_id', 'company_id', 'id'])->on('accounting_journal_entries')->restrictOnDelete();
        });
    }
    public function up(): void
    {
        Schema::table('accounting_open_items', function (Blueprint $t) {
            $t->unsignedInteger('version')->default(1);
            $t->uuid('origin_entry_id')->nullable();
            $t->foreign(['organization_id', 'company_id', 'origin_entry_id'], 'fk_acc_open_items_origin')->references(['organization_id', 'company_id', 'id'])->on('accounting_journal_entries')->restrictOnDelete();
        });
        DB::statement(<<<'SQL'
UPDATE accounting_open_items AS i
SET origin_entry_id = COALESCE(i.opening_entry_id, (
    SELECT MIN(e.id::text COLLATE "C")::uuid
    FROM accounting_journal_entries AS e
    WHERE e.posted_at IS NOT NULL AND e.source_document_id IS NOT NULL
      AND e.organization_id = i.organization_id AND e.company_id = i.company_id
      AND e.source_document_id = i.source_document_id
    HAVING COUNT(*) = 1
))
SQL);
        $this->owned('accounting_opening_batches', function (Blueprint $t) {
            $t->uuid('journal_entry_id')->unique();
            $t->date('cutoff_date');
            $t->string('active_key', 20)->nullable();
            $t->string('data_hash', 64);
            $t->string('status', 24)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->unique(['company_id', 'active_key'], 'uq_acc_active_opening');
        });
        $this->owned('accounting_settlements', function (Blueprint $t) {
            $t->uuid('journal_entry_id')->unique();
            $t->string('reference_hash', 64);
            $t->string('active_key', 64)->nullable();
            $t->string('data_hash', 64);
            $t->string('status', 24)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->unique(['company_id', 'active_key'], 'uq_acc_active_settlement');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('accounting_settlements');
        Schema::dropIfExists('accounting_opening_batches');
        Schema::table('accounting_open_items', function (Blueprint $t) {
            $t->dropForeign('fk_acc_open_items_origin');
            $t->dropColumn(['origin_entry_id', 'version']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\{TenantSchema as Schema, TenantDB as DB};

use Modules\Accounting\Support\PostgresIntegrity as Integrity;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_attachments', function (Blueprint $t) {
            $t->string('title', 160)->default('Soporte conservado');
            $t->text('reason')->nullable();
            $t->uuid('supersedes_id')->nullable();
            $t->string('uploaded_by_name')->nullable();
            $t->unsignedInteger('resource_version')->nullable();
            $t->boolean('after_posting')->default(false);
            $t->json('resource_snapshot')->nullable();
            $t->string('scan_engine', 80)->nullable();
            $t->string('scan_database', 40)->nullable();
            $t->dateTime('scanned_at')->nullable();
            $t->string('preview_path')->nullable();
            $t->string('preview_sha256', 64)->nullable();
            $t->string('identity_hash', 64)->nullable();
            $t->unique(['company_id', 'resource_type', 'resource_id', 'identity_hash'], 'uq_accounting_attachment_content');
            $t->unique(['company_id', 'supersedes_id'], 'uq_accounting_attachment_successor');
            $t->foreign(['organization_id', 'company_id', 'supersedes_id'], 'fk_accounting_attachment_previous')->references(['organization_id', 'company_id', 'id'])->on('accounting_attachments')->restrictOnDelete();
        });
        Schema::table('accounting_audit_events', fn(Blueprint $t) => $t->index(['company_id', 'resource_type', 'resource_id', 'sequence'], 'ix_accounting_audit_case'));
        Integrity::reject('accounting_attachments', 'accounting_attachment_upd', 'UPDATE', 'Soporte conservado inmutable');
        Integrity::reject('accounting_attachments', 'accounting_attachment_del', 'DELETE', 'Soporte conservado inmutable');
    }

    public function down(): void
    {
        Integrity::drop('accounting_attachments', 'accounting_attachment_upd');
        Integrity::drop('accounting_attachments', 'accounting_attachment_del');
        Schema::table('accounting_audit_events', fn(Blueprint $t) => $t->dropIndex('ix_accounting_audit_case'));
        Schema::table('accounting_attachments', function (Blueprint $t) {
            $t->dropForeign('fk_accounting_attachment_previous'); $t->dropUnique('uq_accounting_attachment_content'); $t->dropUnique('uq_accounting_attachment_successor');
            $t->dropColumn(['title', 'reason', 'supersedes_id', 'uploaded_by_name', 'resource_version', 'after_posting', 'resource_snapshot', 'scan_engine', 'scan_database', 'scanned_at', 'preview_path', 'preview_sha256', 'identity_hash']);
        });
    }
};

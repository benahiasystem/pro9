<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->table('accounting_source_versions', function (Blueprint $t) {
            $t->unsignedTinyInteger('integrity_version')->default(1);
            $t->string('integrity_hash', 64)->nullable();
            $t->unsignedInteger('recorded_by')->nullable();
            $t->string('recorded_by_name')->nullable();
            $t->string('origin_kind', 24)->nullable();
            $t->string('disposition', 32)->nullable();
            $t->unsignedInteger('document_version')->nullable();
            $t->json('document_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::connection('tenant')->table('accounting_source_versions')->where('integrity_version', 2)->exists()) {
            throw new RuntimeException('La migración conserva versiones verificables. Una reversión no puede eliminar su evidencia.');
        }
        Schema::connection('tenant')->table('accounting_source_versions', fn(Blueprint $t) => $t->dropColumn(['integrity_version', 'integrity_hash', 'recorded_by', 'recorded_by_name', 'origin_kind', 'disposition', 'document_version', 'document_snapshot']));
    }
};

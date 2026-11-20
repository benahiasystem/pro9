<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_provisioning_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('website_id')->nullable();
            $table->string('tenant_name', 63)->collation('C');
            $table->string('operation', 16)->collation('C');
            $table->string('state', 16)->collation('C');
            $table->json('resources');
            $table->string('error_code', 5)->nullable()->collation('C');
            $table->timestamps();
            $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX tenant_provisioning_pending_unique ON tenant_provisioning_runs (tenant_name) WHERE state NOT IN ('ready','compensated')");
        DB::statement("ALTER TABLE tenant_provisioning_runs ADD CHECK (state IN ('prepared','provisioned','migrated','ready','compensating','compensated','failed'))");
    }
    public function down(): void { Schema::dropIfExists('tenant_provisioning_runs'); }
};
// ######### FIN CAMBIO POSTGRESQL 17

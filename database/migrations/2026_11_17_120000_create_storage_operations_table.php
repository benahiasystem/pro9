<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('storage_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dedup_key',64)->nullable()->unique();
            // Deliberately survives deletion of the website and tenant database.
            $table->string('tenant_uuid', 200)->nullable()->index();
            $table->string('action', 30);
            $table->string('status', 20)->default('pending');
            $table->json('input');
            $table->json('result')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('diagnostic')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('storage_operations'); }
};
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

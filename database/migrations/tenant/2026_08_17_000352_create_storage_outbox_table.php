<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('storage_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('operation_key')->unique();
            $table->string('action', 30);
            $table->string('path', 255)->collation('C');
            $table->string('source', 255)->nullable();
            $table->string('sha256', 64)->collation('C')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('diagnostic')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }
    public function down(): void { Schema::dropIfExists('storage_outbox'); }
};
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

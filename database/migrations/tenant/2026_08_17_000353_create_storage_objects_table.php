<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('storage_objects', function (Blueprint $table) {
            $table->string('path', 255)->collation('C')->primary();
            $table->string('object_key', 255)->collation('C')->unique();
            $table->uuid('version')->unique();
            $table->string('sha256', 64)->collation('C');
            $table->unsignedBigInteger('size');
            $table->string('mime',120);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('storage_objects'); }
};
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

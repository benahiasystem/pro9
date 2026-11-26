<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('storage_operation_files',function(Blueprint $table){
            $table->bigIncrements('id');$table->uuid('operation_id');
            $table->string('path', 255)->collation('C');
            $table->unsignedBigInteger('size');$table->boolean('deleted')->default(false);
            $table->unique(['operation_id','path'],'storage_manifest_path_unique');
            $table->index(['operation_id','deleted','id']);
            $table->foreign('operation_id')->references('id')->on('storage_operations')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('storage_operation_files'); }
};
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

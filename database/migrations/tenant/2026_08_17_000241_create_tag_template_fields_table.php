<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - type: varchar(100)
 * - column: varchar(255)
 * - x: varchar(255)
 * - y: varchar(255)
 * - width: varchar(255)
 * - height: varchar(255)
 * - style: json
 * - barcode: json
 * - image: varchar(255)
 * - html_id: varchar(255)
 * - has_image: boolean
 * - tag_template_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_template_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 100)->collation('pro9_ci_ai');
            $table->string('column', 255)->collation('pro9_ci_ai');
            $table->string('x', 255)->collation('pro9_ci_ai');
            $table->string('y', 255)->collation('pro9_ci_ai');
            $table->string('width', 255)->collation('pro9_ci_ai');
            $table->string('height', 255)->collation('pro9_ci_ai');
            $table->json('style')->nullable();
            $table->json('barcode')->nullable();
            $table->string('image', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('html_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('has_image')->default(false);
            $table->bigInteger('tag_template_id');
            $table->index(['tag_template_id'], 'tag_template_fields_tag_template_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_template_fields');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - description: varchar(255)
 * - type: varchar(255)
 * - image: varchar(255)
 * - item_id: bigint
 * - category_id: bigint
 * - custom_link: varchar(255)
 * - spot_url: varchar(255)
 * - status: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - apply_restaurant: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->string('type', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('image', 255)->collation('pro9_ci_ai');
            $table->bigInteger('item_id')->nullable();
            $table->bigInteger('category_id')->nullable();
            $table->string('custom_link', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('spot_url', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('apply_restaurant')->nullable()->default(false);
            $table->index(['item_id'], 'promotions_item_id_foreign');
            $table->index(['category_id'], 'promotions_category_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

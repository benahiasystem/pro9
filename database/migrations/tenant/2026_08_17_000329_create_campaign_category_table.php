<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - discount_campaign_id: bigint
 * - category_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_category', function (Blueprint $table) {
            $table->bigInteger('discount_campaign_id');
            $table->bigInteger('category_id');
            $table->primary(['discount_campaign_id', 'category_id']);
            $table->index(['category_id'], 'campaign_category_category_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_category');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

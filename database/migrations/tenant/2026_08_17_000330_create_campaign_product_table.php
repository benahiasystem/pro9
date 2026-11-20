<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - discount_campaign_id: bigint
 * - item_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_product', function (Blueprint $table) {
            $table->bigInteger('discount_campaign_id');
            $table->bigInteger('item_id');
            $table->primary(['discount_campaign_id', 'item_id']);
            $table->index(['item_id'], 'campaign_product_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_product');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

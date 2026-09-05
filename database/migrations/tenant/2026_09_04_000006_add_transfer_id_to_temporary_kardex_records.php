<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ########### INICIO CONTRATO FLUJO DE PRODUCTOS
        if (Schema::hasTable('temporary_kardex_records') && ! Schema::hasColumn('temporary_kardex_records', 'transfer_id')) {
            Schema::table('temporary_kardex_records', function (Blueprint $table) {
                $table->unsignedInteger('transfer_id')->nullable()->after('guide_id');
            });
        }
        // ########### FIN CONTRATO FLUJO DE PRODUCTOS
    }

    public function down(): void
    {
        // ########### INICIO CONTRATO FLUJO DE PRODUCTOS
        if (Schema::hasTable('temporary_kardex_records') && Schema::hasColumn('temporary_kardex_records', 'transfer_id')) {
            Schema::table('temporary_kardex_records', function (Blueprint $table) {
                $table->dropColumn('transfer_id');
            });
        }
        // ########### FIN CONTRATO FLUJO DE PRODUCTOS
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('system')->create('marketplace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'int', 'bool', 'json'])->default('string');
            $table->timestamp('updated_at')->nullable();
        });

        // Defaults en la propia migración: no hay seeder.
        DB::connection('system')->table('marketplace_settings')->insert([
            [
                // El marketplace nace apagado: nada es público hasta que el admin
                // lo enciende deliberadamente desde Ajustes.
                'key' => 'is_enabled',
                'value' => '0',
                'type' => 'bool',
            ],
            [
                'key' => 'title',
                'value' => 'Marketplace',
                'type' => 'string',
            ],
            [
                'key' => 'description',
                'value' => 'Encuentra productos y servicios cerca de ti',
                'type' => 'string',
            ],
            [
                'key' => 'community_name',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'whatsapp_greeting',
                'value' => 'Hola, vi este producto en el marketplace y me interesa:',
                'type' => 'string',
            ],
            [
                'key' => 'max_items_per_store',
                'value' => '500',
                'type' => 'int',
            ],
            [
                'key' => 'items_per_page',
                'value' => '24',
                'type' => 'int',
            ],
            [
                'key' => 'report_reasons',
                'value' => json_encode([
                    'Contenido inapropiado',
                    'Producto copiado',
                    'Información falsa',
                    'Spam',
                    'Otro',
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'json',
            ],
        ]);
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_settings');
    }
};

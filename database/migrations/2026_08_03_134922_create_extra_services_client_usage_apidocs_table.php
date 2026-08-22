<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('extra_services_client_usage_apidocs', function (Blueprint $table) {
            $table->id(); // Esto crea un BIGINT, que está bien para el ID de esta tabla nueva
    
        // CAMBIA A ESTO:
        $table->unsignedInteger('client_id'); 
        
        $table->string('month');
        $table->integer('quantity');
        $table->timestamps();

        // Ahora sí coincidirán los tipos:
        $table->foreign('client_id')
            ->references('id')
            ->on('clients')
            ->onDelete('cascade');
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('extra_services_client_usage_apidocs');
    }
};

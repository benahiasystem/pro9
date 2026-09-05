<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TenantCreateDashboardLayoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        // ########### INICIO: permitir reordenar la migración sin duplicar la tabla existente ###########
        if (Schema::hasTable('dashboard_layouts')) {
            return;
        }
        // ########### FIN: permitir reordenar la migración sin duplicar la tabla existente ###########

        Schema::create('dashboard_layouts', function(Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->json('layout');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('dashboard_layouts');
    }
}

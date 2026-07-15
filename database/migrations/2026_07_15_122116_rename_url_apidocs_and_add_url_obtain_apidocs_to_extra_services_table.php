<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->string('urlObtainApidocs')->nullable()->after('id');
        });

        Schema::table('extra_services', function (Blueprint $table) {
            $table->renameColumn('urlApidocs', 'urlServiceApidocs');
        });

        // Reordenar columnas (MySQL)
        DB::statement('ALTER TABLE extra_services MODIFY urlServiceApidocs VARCHAR(255) NULL AFTER urlObtainApidocs');
        DB::statement('ALTER TABLE extra_services MODIFY isActiveApidocs TINYINT(1) NOT NULL DEFAULT 0 AFTER urlServiceApidocs');
    }

    public function down()
    {
        DB::statement('ALTER TABLE extra_services MODIFY isActiveApidocs TINYINT(1) NOT NULL DEFAULT 0 AFTER id');

        Schema::table('extra_services', function (Blueprint $table) {
            $table->renameColumn('urlServiceApidocs', 'urlApidocs');
        });

        DB::statement('ALTER TABLE extra_services MODIFY urlApidocs VARCHAR(255) NULL AFTER isActiveApidocs');

        Schema::table('extra_services', function (Blueprint $table) {
            $table->dropColumn('urlObtainApidocs');
        });
    }
};
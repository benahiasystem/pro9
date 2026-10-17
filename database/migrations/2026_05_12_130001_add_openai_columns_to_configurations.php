<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOpenaiColumnsToConfigurations extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('openai_api_key', 255)->nullable()->after('evolution_webhook_token');
            $table->string('openai_model', 100)->nullable()->default('gpt-4o-mini')->after('openai_api_key');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            foreach (['openai_api_key', 'openai_model'] as $column) {
                if (Schema::hasColumn('configurations',$column)) $table->dropColumn($column);
            }
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGitRepositoryToSystemConfigurations extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->text('git_remote_url')->nullable();
            $table->string('git_user', 191)->nullable();
            $table->text('git_token')->nullable();
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['git_remote_url', 'git_user', 'git_token']);
        });
    }
}

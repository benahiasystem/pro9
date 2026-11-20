<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - seller_id: bigint
 * - date: date
 * - time: time
 * - token: varchar(255)
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorized_discount_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('seller_id')->nullable();
            $table->date('date');
            $table->time('time');
            $table->string('token', 255)->collation('C');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['token'], 'authorized_discount_users_token_unique');
            $table->index(['user_id'], 'authorized_discount_users_user_id_foreign');
            $table->index(['seller_id'], 'authorized_discount_users_seller_id_foreign');
            $table->index(['date'], 'authorized_discount_users_date_index');
            $table->index(['active'], 'authorized_discount_users_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_discount_users');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

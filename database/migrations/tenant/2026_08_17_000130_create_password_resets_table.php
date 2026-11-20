<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - email: varchar(255)
 * - token: varchar(255)
 * - created_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email', 255)->collation('sab_ci_ai');
            $table->string('token', 255)->collation('C');
            $table->timestamp('created_at')->nullable();
            $table->index(['email'], 'password_resets_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

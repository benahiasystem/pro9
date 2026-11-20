<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - key: varchar(255)
 * - value: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 255)->collation('sab_ci_ai');
            $table->text('value')->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['key'], 'claim_settings_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_settings');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

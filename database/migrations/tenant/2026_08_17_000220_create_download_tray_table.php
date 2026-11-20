<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - module: varchar(255)
 * - format: varchar(255)
 * - path: varchar(255)
 * - file_name: varchar(255)
 * - type: varchar(255)
 * - status: varchar(255)
 * - date_init: timestamp
 * - date_end: timestamp
 * - payload_request: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_tray', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->nullable();
            $table->string('module', 255)->collation('sab_ci_ai');
            $table->string('format', 255)->collation('sab_ci_ai');
            $table->string('path', 255)->collation('sab_ci_ai')->nullable();
            $table->string('file_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('type', 255)->collation('sab_ci_ai')->nullable();
            $table->string('status', 255)->collation('sab_ci_ai')->default('IN_PROCESS');
            $table->timestamp('date_init')->nullable();
            $table->timestamp('date_end')->nullable();
            $table->text('payload_request')->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'download_tray_user_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_tray');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

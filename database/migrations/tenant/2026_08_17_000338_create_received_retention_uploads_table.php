<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - token: char(36)
 * - user_id: bigint
 * - document_id: bigint
 * - path: varchar(255)
 * - expires_at: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_retention_uploads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('token', 36)->collation('pro9_ci_ai');
            $table->bigInteger('user_id');
            $table->bigInteger('document_id')->nullable();
            $table->string('path', 255)->collation('pro9_ci_ai');
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['token'], 'retention_upload_token_unique');
            $table->index(['expires_at'], 'retention_upload_expiry_index');
        });
        // Preserve the implicit first-TIMESTAMP rule captured on the MySQL reference.
        \App\Database\PostgresBootstrap::installTouchTrigger(DB::connection(), 'received_retention_uploads', 'expires_at');
    }

    public function down(): void
    {
        Schema::dropIfExists('received_retention_uploads');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

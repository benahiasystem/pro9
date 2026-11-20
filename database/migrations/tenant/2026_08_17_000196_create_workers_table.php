<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - identity_document_type_id: varchar(255)
 * - number: varchar(255)
 * - name: varchar(255)
 * - birth_date: date
 * - admission_date: date
 * - occupation: varchar(255)
 * - address: varchar(255)
 * - email: varchar(255)
 * - telephone: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identity_document_type_id', 255)->collation('sab_ci_ai');
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->date('birth_date');
            $table->date('admission_date');
            $table->string('occupation', 255)->collation('sab_ci_ai');
            $table->string('address', 255)->collation('sab_ci_ai')->nullable();
            $table->string('email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('telephone', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['identity_document_type_id'], 'workers_identity_document_type_id_foreign');
            $table->index(['number'], 'workers_number_index');
            $table->index(['name'], 'workers_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

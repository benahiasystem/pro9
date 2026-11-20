<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - number: varchar(255)
 * - name: varchar(255)
 * - identity_document_type_id: varchar(255)
 * - sex: varchar(255)
 * - age: integer
 * - civil_status: varchar(255)
 * - nacionality: varchar(255)
 * - origin: varchar(255)
 * - room_number: integer
 * - date_entry: date
 * - time_entry: time
 * - date_exit: date
 * - time_exit: time
 * - ocupation: varchar(255)
 * - room_type: varchar(255)
 * - guests: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_hotels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('identity_document_type_id', 255)->collation('sab_ci_ai');
            $table->string('sex', 255)->collation('sab_ci_ai');
            $table->integer('age');
            $table->string('civil_status', 255)->collation('sab_ci_ai');
            $table->string('nacionality', 255)->collation('sab_ci_ai');
            $table->string('origin', 255)->collation('sab_ci_ai');
            $table->integer('room_number');
            $table->date('date_entry');
            $table->time('time_entry');
            $table->date('date_exit');
            $table->time('time_exit');
            $table->string('ocupation', 255)->collation('sab_ci_ai')->nullable();
            $table->string('room_type', 255)->collation('sab_ci_ai')->nullable();
            $table->json('guests')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['document_id'], 'document_hotels_document_id_foreign');
            $table->index(['identity_document_type_id'], 'document_hotels_identity_document_type_id_foreign');
        });
        DB::statement('ALTER TABLE "document_hotels" ADD CONSTRAINT "document_hotels_sex_check" CHECK ("sex" IN (\'M\',\'F\'))');
        DB::statement('ALTER TABLE "document_hotels" ADD CONSTRAINT "document_hotels_age_range" CHECK ("age" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "document_hotels" ADD CONSTRAINT "document_hotels_civil_status_check" CHECK ("civil_status" IN (\'S\',\'C\',\'V\',\'D\'))');
        DB::statement('ALTER TABLE "document_hotels" ADD CONSTRAINT "document_hotels_room_number_range" CHECK ("room_number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_hotels');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

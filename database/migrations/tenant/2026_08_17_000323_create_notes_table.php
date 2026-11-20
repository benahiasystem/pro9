<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - note_type: varchar(255)
 * - note_credit_type_id: varchar(255)
 * - note_debit_type_id: varchar(255)
 * - note_description: varchar(255)
 * - affected_document_id: bigint
 * - data_affected_document: json
 * - creation_key: char(36)
 * - request_hash: char(64)
 * - effect_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('note_type', 255)->collation('sab_ci_ai');
            $table->string('note_credit_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('note_debit_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('note_description', 255)->collation('sab_ci_ai');
            $table->bigInteger('affected_document_id')->nullable();
            $table->json('data_affected_document')->nullable();
            $table->char('creation_key', 36)->collation('sab_ci_ai')->nullable();
            $table->char('request_hash', 64)->collation('C')->nullable();
            $table->json('effect_snapshot')->nullable();
            $table->unique(['creation_key'], 'notes_creation_key_unique');
            $table->unique(['document_id'], 'notes_document_unique');
            $table->index(['document_id'], 'notes_document_id_foreign');
            $table->index(['note_credit_type_id'], 'notes_note_credit_type_id_foreign');
            $table->index(['note_debit_type_id'], 'notes_note_debit_type_id_foreign');
            $table->index(['affected_document_id'], 'notes_affected_document_id_foreign');
        });
        DB::statement('ALTER TABLE "notes" ADD CONSTRAINT "notes_note_type_check" CHECK ("note_type" IN (\'credit\',\'debit\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17

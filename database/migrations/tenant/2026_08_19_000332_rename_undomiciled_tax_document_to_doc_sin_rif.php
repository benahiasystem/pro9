<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ########## INICIO CAMBIO SUNAT A SENIAT
    public function up(): void
    {
        DB::table('cat_identity_document_types')
            ->where('id', '0')
            ->update(['description' => 'Doc.sin.rif']);
    }

    public function down(): void
    {
        DB::table('cat_identity_document_types')
            ->where('id', '0')
            ->update(['description' => 'Doc.trib.no.dom.sin.ruc']);
    }
    // ######### FIN CAMBIO SUNAT A SENIAT
};

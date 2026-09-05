<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantRenameIdentityDocumentTypeDescriptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('cat_identity_document_types')
            ->where('id', '0')
            ->update(['description' => 'No Domiciliado sin Ruc']);

        DB::table('cat_identity_document_types')
            ->where('id', '4')
            ->update(['description' => 'Carnet de Extranjeria']);

        Cache::forget('identity_document_types');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('cat_identity_document_types')
            ->where('id', '0')
            ->update(['description' => 'Doc.trib.no.dom.sin.ruc']);

        DB::table('cat_identity_document_types')
            ->where('id', '4')
            ->update(['description' => 'CE']);

        Cache::forget('identity_document_types');
    }
}

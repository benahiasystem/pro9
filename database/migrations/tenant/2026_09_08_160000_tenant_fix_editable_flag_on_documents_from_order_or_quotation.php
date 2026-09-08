<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige CPE en estado Registrado provenientes de pedido/cotización
 * que quedaron con is_editable = 0 (default de la columna) y no mostraban Editar.
 */
class TenantFixEditableFlagOnDocumentsFromOrderOrQuotation extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('documents') || !Schema::hasColumn('documents', 'is_editable')) {
            return;
        }

        $query = DB::table('documents')
            ->where('is_editable', 0)
            ->where('state_type_id', '01');

        $query->where(function ($q) {
            $q->whereNotNull('order_note_id');
            if (Schema::hasColumn('documents', 'quotation_id')) {
                $q->orWhereNotNull('quotation_id');
            }
        });

        $query->update(['is_editable' => 1]);
    }

    public function down()
    {
        // No revierte: el flag pudo corregirse a propósito.
    }
}

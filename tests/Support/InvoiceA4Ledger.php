<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Schema;

/** Minimal in-memory ledger for local A4 presentation, never an application database. */
final class InvoiceA4Ledger
{
    public static function create(): void
    {
        $schema=Schema::connection('tenant');
        $schema->create('documents', function ($t) {
            $t->increments('id'); foreach (['document_type_id','state_type_id','series','number','control_number','currency_type_id','customer_id','establishment_id','total','total_payable','fiscal_emission_mode'] as $field) $t->string($field)->nullable();
        });
        $schema->create('notes', function ($t) {
            $t->increments('id'); foreach (['document_id','affected_document_id','creation_key','note_debit_type_id'] as $field) $t->string($field)->nullable();
        });
        $schema->create('document_payments', function ($t) {
            $t->increments('id'); foreach (['document_id','receipt_parent_id','reversed_at','payment_method_type_id','currency_type_id','exchange_rate','original_amount','payment','tax_amount','change','calculation_snapshot','payment_method_snapshot'] as $field) $t->text($field)->nullable();
        });
        $schema->create('document_taxes', function ($t) {
            $t->increments('id'); foreach (['document_id','document_payment_id','tax_kind','percentage','amount','calculation_snapshot'] as $field) $t->text($field)->nullable();
        });
        $schema->create('document_emissions', function ($t) { $t->increments('id'); $t->integer('document_id'); $t->string('status'); });
        $schema->create('payment_method_types', function ($t) { $t->string('id')->primary(); });
        $schema->create('document_note_adjustments', function ($t) {
            $t->increments('id'); foreach (['document_id','affected_document_id','kind','tax_id','amount','status','reversed_at','calculation_snapshot'] as $field) $t->text($field)->nullable();
        });
        $schema->create('document_note_refunds', function ($t) {
            $t->increments('id'); foreach (['document_id','affected_document_id','adjustment_id','component','amount','original_amount','currency_type_id','reversed_at','calculation_snapshot'] as $field) $t->text($field)->nullable();
        });
    }
}

<?php

namespace Modules\Accounting\Tests;

/** Minimal native PRO9 tables, only for the guarded accounting_module_test DB. */
final class NativeSourceTestSchema
{
    public static function create(mixed $schema): void
    {
        if (!$schema->hasTable('companies')) {
            $schema->create('companies', function ($t) { $t->increments('id'); $t->string('name'); $t->string('number'); });
        }
        foreach (['documents' => 'customer', 'purchases' => 'supplier'] as $table => $party) {
            if (!$schema->hasTable($table)) {
                $schema->create($table, function ($t) use ($party, $table) {
                    $t->increments('id'); $t->string('series'); $t->unsignedInteger('number'); $t->string('document_type_id', 2); $t->date('date_of_issue'); $t->string('state_type_id', 2); $t->string('currency_type_id', 3); $t->json($party);
                    foreach (['total', 'total_value', 'total_igv', 'total_taxes', 'exchange_rate_sale'] as $field) { $t->decimal($field, 38, 12); }
                    $t->json('calculation_snapshot')->nullable(); $t->string('control_number')->nullable(); $t->string('exchange_rate_source')->nullable(); $t->date('exchange_rate_date')->nullable(); $t->string('fiscal_emission_mode')->default('local'); $t->timestamps();
                    if ($table === 'purchases') { $t->softDeletes(); }
                });
            }
        }
        if (!$schema->hasTable('document_emissions')) {
            $schema->create('document_emissions', function ($t) { $t->increments('id'); $t->unsignedInteger('document_id'); $t->string('status'); });
        }
    }
}

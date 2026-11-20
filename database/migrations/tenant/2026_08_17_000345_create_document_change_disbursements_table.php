<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Instalaciones nuevas: desembolsos de vuelto, con referencias bigint y fracciones exactas.
 * Importes numeric(30,12), tasa numeric(18,8), posición entera de 0 a 65535,
 * JSON original, fechas locales y una fila por cobro/posición. FKs en 000999.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_change_disbursements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_payment_id');
            $table->unsignedSmallInteger('position');
            $table->char('payment_method_type_id', 2)->collation('sab_ci_ai');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('original_amount', 30, 12);
            $table->decimal('exchange_rate', 18, 8);
            $table->date('date_of_payment');
            $table->string('reference', 255)->collation('sab_ci_ai')->nullable();
            $table->json('calculation_snapshot');
            $table->json('context');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['document_payment_id', 'position'], 'document_change_payment_position_unique');
            $table->index('document_id', 'document_change_document_index');
            $table->index('payment_method_type_id', 'document_change_method_index');
            $table->index('currency_type_id', 'document_change_currency_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_change_disbursements');
    }
};

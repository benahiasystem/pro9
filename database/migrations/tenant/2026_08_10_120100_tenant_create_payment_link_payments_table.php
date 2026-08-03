<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_link_payments', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('payment_link_id');

            // registro que se va a cobrar con el link (comprobante), nulo para cobros sin comprobante
            $table->unsignedInteger('record_id')->nullable();
            $table->string('record_type')->nullable();

            // pago generado, se registra recien cuando el link es pagado
            $table->unsignedInteger('payment_id')->nullable();
            $table->string('payment_type')->nullable();

            // monto del pago aplicado al link
            $table->decimal('total', 12, 2)->nullable();

            // pending: el link aun no fue pagado | paid: ya se registró el pago
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            $table->index(['record_id', 'record_type'], 'payment_link_payments_record_index');
            $table->index(['payment_id', 'payment_type'], 'payment_link_payments_payment_index');
            $table->index(['payment_link_id', 'status'], 'payment_link_payments_status_index');

            $table->foreign('payment_link_id')->references('id')->on('payment_links')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_link_payments');
    }
};

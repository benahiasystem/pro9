<?php

use App\Models\Tenant\{
    Document,
    DocumentPayment,
};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Payment\Models\PaymentLinkPayment;

/**
 * Migra los pagos asociados a payment_links (payment_id/payment_type)
 * hacia la tabla intermedia payment_link_payments
 *
 * Son links que ya fueron pagados, por eso se registran con estado pagado
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = now();

        DB::table('payment_links')
            ->whereNotNull('payment_id')
            ->whereNotNull('payment_type')
            ->orderBy('id')
            ->chunkById(500, function ($payment_links) use ($now) {

                $rows = [];

                foreach ($payment_links as $payment_link) {

                    $rows[] = [
                        'payment_link_id' => $payment_link->id,
                        'record_id' => $this->getRecordId($payment_link),
                        'record_type' => $payment_link->payment_type === DocumentPayment::class ? Document::class : null,
                        'payment_id' => $payment_link->payment_id,
                        'payment_type' => $payment_link->payment_type,
                        'total' => $payment_link->total,
                        'status' => PaymentLinkPayment::STATUS_PAID,
                        'created_at' => $payment_link->created_at ?? $now,
                        'updated_at' => $payment_link->updated_at ?? $now,
                    ];

                }

                if (!empty($rows)) {
                    DB::table('payment_link_payments')->insert($rows);
                }

            });
    }

    /**
     * Obtener el comprobante origen del pago asociado al link
     *
     * @param  object $payment_link
     * @return int|null
     */
    private function getRecordId($payment_link)
    {
        if($payment_link->payment_type !== DocumentPayment::class) return null;

        return DB::table('document_payments')->where('id', $payment_link->payment_id)->value('document_id');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('payment_link_payments')->truncate();
    }
};

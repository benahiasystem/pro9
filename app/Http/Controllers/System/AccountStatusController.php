<?php
namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ClientPaymentRequest;
use App\Http\Requests\System\DocumentRequest;
use App\Http\Resources\System\ClientPaymentCollection;
use App\Models\System\Client;
use App\Models\System\ClientPayment;
use App\Models\System\PaymentMethodType;
use App\Models\System\CardBrand;

class AccountStatusController extends Controller
{
    public function records($client_id)
    {
        $records = ClientPayment::where('client_id', $client_id)->get();

        return new ClientPaymentCollection($records);
    }

    public function tables()
    {
        return [
            'payment_method_types' => PaymentMethodType::all(),
            'card_brands' => CardBrand::all()
        ];
    }

    public function client($client_id)
    {
        $client = Client::find($client_id);

        $totals=\App\Support\Exact\AccountTotals::payments($client->payments()->lazyById(500));
        $image_url = (\App\Support\Exact\Decimal::read($totals,'total_due')->isGreaterThan(0)) ? asset('/logo/sad.png') : asset('/logo/happy.png');

        return [
            'totals' => $totals,
            'client' => $client, 
            'image_url' => $image_url
        ];

    } 


}

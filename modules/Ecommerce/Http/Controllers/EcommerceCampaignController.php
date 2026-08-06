<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EcommerceCampaign;
use Illuminate\Http\Request;

class EcommerceCampaignController extends Controller
{
    public function records()
    {
        $records = EcommerceCampaign::orderBy('id', 'desc')->get();

        return compact('records');
    }

    public function record($id)
    {
        $record = EcommerceCampaign::findOrFail($id);

        return compact('record');
    }

    public function store(Request $request)
    {
        $id = $request->input('id');
        $campaign = EcommerceCampaign::firstOrNew(['id' => $id]);
        $campaign->fill($request->only($campaign->getFillable()));

        $campaign->status = (bool) $request->input('status', true);
        $campaign->sp_countdown = (bool) $request->input('sp_countdown', false);
        $campaign->sp_discount_price = (bool) $request->input('sp_discount_price', false);
        $campaign->sp_purchase_count = (bool) $request->input('sp_purchase_count', false);
        $campaign->sp_views_count = (bool) $request->input('sp_views_count', false);
        $campaign->sp_stock_alert = (bool) $request->input('sp_stock_alert', false);
        $campaign->sp_rating = (bool) $request->input('sp_rating', false);
        $campaign->sp_product_ids = array_values(array_map('intval', (array) $request->input('sp_product_ids', [])));
        $campaign->sp_stock_threshold = max(1, (int) $request->input('sp_stock_threshold', 10));

        $campaign->save();
        EcommerceCampaign::forgetActiveCache();

        return [
            'success' => true,
            'message' => $id ? 'Campaña editada con éxito' : 'Campaña registrada con éxito',
            'id' => $campaign->id,
        ];
    }

    public function destroy($id)
    {
        $campaign = EcommerceCampaign::findOrFail($id);
        $campaign->delete();
        EcommerceCampaign::forgetActiveCache();

        return [
            'success' => true,
            'message' => 'Campaña eliminada con éxito',
        ];
    }

    public function status($id)
    {
        $campaign = EcommerceCampaign::findOrFail($id);
        $campaign->status = ! $campaign->status;
        $campaign->save();
        EcommerceCampaign::forgetActiveCache();

        return [
            'success' => true,
            'message' => 'Estado de la campaña actualizado con éxito',
        ];
    }
}

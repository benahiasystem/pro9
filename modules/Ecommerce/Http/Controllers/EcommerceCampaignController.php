<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EcommerceCampaign;
use Illuminate\Http\Request;

class EcommerceCampaignController extends Controller
{
    public function records()
    {
        // Solo puede existir una campaña: conservar la más reciente.
        $all = EcommerceCampaign::orderByDesc('id')->get();
        if ($all->count() > 1) {
            $keepId = (int) $all->first()->id;
            EcommerceCampaign::where('id', '!=', $keepId)->delete();
            EcommerceCampaign::forgetActiveCache();
            $all = EcommerceCampaign::orderByDesc('id')->get();
        }

        $records = $all
            ->map(fn (EcommerceCampaign $campaign) => $campaign->toAdminArray())
            ->values();

        return compact('records');
    }

    public function record($id)
    {
        $record = EcommerceCampaign::findOrFail($id)->toAdminArray();

        return compact('record');
    }

    public function store(Request $request)
    {
        $id = $request->input('id');

        if (! $id && EcommerceCampaign::query()->exists()) {
            return [
                'success' => false,
                'message' => 'Solo puedes tener una campaña. Edita la existente.',
            ];
        }

        $campaign = EcommerceCampaign::firstOrNew(['id' => $id]);
        $campaign->fill($request->only($campaign->getFillable()));

        $campaign->status = (bool) $request->input('status', true);
        $campaign->sp_countdown = (bool) $request->input('sp_countdown', false);
        $campaign->sp_discount_price = (bool) $request->input('sp_discount_price', false);
        $campaign->sp_purchase_count = (bool) $request->input('sp_purchase_count', false);
        $campaign->sp_views_count = (bool) $request->input('sp_views_count', false);
        $campaign->sp_stock_alert = (bool) $request->input('sp_stock_alert', false);
        $campaign->sp_rating = (bool) $request->input('sp_rating', false);
        // Campaña global: aplica a todos los productos.
        $campaign->sp_product_ids = [];
        $campaign->sp_stock_threshold = max(1, (int) $request->input('sp_stock_threshold', 10));

        // Guardar fechas en hora local exacta.
        $campaign->start_date = EcommerceCampaign::normalizeDateTime($request->input('start_date'));
        $campaign->end_date = EcommerceCampaign::normalizeDateTime($request->input('end_date'));

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

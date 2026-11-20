<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Ecommerce\Models\Tenant\DiscountCampaign;
use App\Models\Tenant\Item;
use Modules\Item\Models\Category;
use Modules\Ecommerce\Services\CampaignPriceService;
use App\Support\Exact\Decimal as D;

class DiscountCampaignController extends Controller
{
    public function records()
    {
        DiscountCampaign::deactivateExpired();
        $records = DiscountCampaign::with(['products:id', 'categories:id'])->latest()->get()->map(function (DiscountCampaign $campaign) {
            return $this->serialize($campaign);
        });

        return compact('records');
    }

    public function record($id)
    {
        return ['record' => $this->serialize(DiscountCampaign::with(['products:id', 'categories:id'])->findOrFail($id))];
    }

    public function options()
    {
        $products = Item::where('apply_store', 1)->orderBy('description')->get(['id', 'description', 'internal_id'])->map(fn ($item) => [
            'id' => $item->id,
            'name' => trim(($item->internal_id ? $item->internal_id.' - ' : '').$item->description),
        ]);
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return compact('products', 'categories');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', Rule::in(['percentage'])],
            'value' => ['required', 'numeric', 'gt:0'],
            'expires_at' => ['required', 'date', 'after:now'],
            'is_active' => ['boolean'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'exists:tenant.items,id'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:tenant.categories,id'],
        ], [
            'expires_at.required' => 'Selecciona la fecha y hora de vencimiento.',
            'expires_at.date' => 'La fecha y hora de vencimiento no es válida.',
            'expires_at.after' => 'La fecha de vencimiento debe ser posterior a la fecha y hora actual.',
        ]);

        // ######## INICIO CALCULOS EXACTOS SAB ########
        $data['value'] = D::input($data['value'], 'value');
        if (D::of($data['value'])->isGreaterThan(100)) {
            return response()->json([
                'message' => 'El porcentaje no puede ser mayor a 100%.',
                'errors' => ['value' => ['El porcentaje no puede ser mayor a 100%.']],
            ], 422);
        }
        // ######## FIN CALCULOS EXACTOS SAB ########

        if (empty($data['product_ids']) && empty($data['category_ids'])) {
            return response()->json([
                'message' => 'Selecciona al menos un producto o una categoría.',
                'errors' => ['product_ids' => ['Selecciona al menos un producto o una categoría.']],
            ], 422);
        }

        $campaign = DiscountCampaign::firstOrNew(['id' => $data['id'] ?? null]);
        $wasActive = $campaign->exists && $campaign->is_active;
        $campaign->fill($data);
        $campaign->is_active = (bool) ($data['is_active'] ?? true);
        if ($campaign->is_active && ! $wasActive) {
            $campaign->starts_at = now();
        }
        $campaign->save();
        $campaign->products()->sync($data['product_ids'] ?? []);
        $campaign->categories()->sync($data['category_ids'] ?? []);

        return [
            'success' => true,
            'message' => $campaign->wasRecentlyCreated ? 'Campaña creada correctamente.' : 'Campaña actualizada correctamente.',
            'record' => $this->serialize($campaign),
        ];
    }

    public function status(Request $request, $id)
    {
        $campaign = DiscountCampaign::findOrFail($id);
        $newStatus = (bool) $request->input('is_active', ! $campaign->is_active);
        if ($newStatus && ! $campaign->is_active) {
            $campaign->starts_at = now();
        }
        $campaign->is_active = $newStatus;
        $campaign->save();

        return ['success' => true, 'message' => 'Estado actualizado correctamente.'];
    }

    public function destroy($id)
    {
        DiscountCampaign::findOrFail($id)->delete();

        return ['success' => true, 'message' => 'Campaña eliminada correctamente.'];
    }

    public function validateCart(Request $request)
    {
        if (!\App\Models\Tenant\ConfigurationEcommerce::storefrontShowsPrices()) {
            abort(403,'La tienda no muestra precios en esta modalidad de cotización.');
        }
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:tenant.items,id'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $items = Item::whereIn('id', collect($data['items'])->pluck('item_id'))->get()->keyBy('id');
        $pricingService = app(CampaignPriceService::class);

        $details = [];
        // ######## INICIO CALCULOS EXACTOS SAB ########
        $totalDiscount = D::of(0);
        $cartSubtotal = D::of(0);
        $rate = null;

        foreach ($data['items'] as $index=>$line) {
            $item = $items->get($line['item_id']);
            $quantity = D::of(D::input($line['quantity'] ?? '1', 'items.'.$index.'.quantity'));
            if (!$quantity->isPositive()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items.'.$index.'.quantity'=>'La cantidad debe ser positiva.']);
            }
            $pricing = $pricingService->forItem($item);
            $currency=(string)$item->currency_type_id;
            if (!in_array($currency,['VES','USD'],true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items.'.$index.'.item_id'=>'El producto requiere una moneda vigente.']);
            }
            if ($currency==='USD' && $rate===null) $rate=\Modules\Ecommerce\Services\StorefrontCheckoutCalculation::exchangeRate();
            $base=D::readRequired($pricing,'base_price')->multipliedBy($quantity);
            $cartSubtotal = $cartSubtotal->plus(D::convert($base,$currency,'VES',$rate ?? '1'));
            $discount = D::readRequired($pricing,'real_discount')->multipliedBy($quantity);
            $totalDiscount = $totalDiscount->plus(D::convert($discount,$currency,'VES',$rate ?? '1'));
            $values = ['base_unit_price'=>D::readRequired($pricing,'base_price'),
                'final_unit_price'=>D::readRequired($pricing,'final_price'),
                'percentage'=>D::readRequired($pricing,'real_discount_percentage'),
                'discount'=>$discount, 'quantity'=>$quantity];
            if ($pricing['compare_at_price'] !== null) $values['compare_at_price']=D::readRequired($pricing,'compare_at_price');
            $details[] = ['item_id'=>$item->id, 'campaign_id'=>$pricing['discount_campaign_id'],
                'currency_type_id'=>$currency,
                'campaign'=>$pricing['discount_campaign_name'], 'percentage'=>$pricing['real_discount_percentage'],
                'quantity'=>D::represent($quantity), 'base_unit_price'=>$pricing['base_price'],
                'compare_at_price'=>$pricing['compare_at_price'], 'final_unit_price'=>$pricing['final_price'],
                'discount'=>D::represent($discount,2,true), 'calculation_snapshot'=>D::snapshot($values)];
        }

        $total=$cartSubtotal->minus($totalDiscount);
        return ['success'=>true, 'currency_type_id'=>'VES', 'subtotal'=>D::represent($cartSubtotal,2,true),
            'discount'=>D::represent($totalDiscount,2,true), 'total'=>D::represent($total,2,true),
            'items'=>$details, 'calculation_snapshot'=>D::snapshot(['subtotal'=>$cartSubtotal,'discount'=>$totalDiscount,'total'=>$total])];
        // ######## FIN CALCULOS EXACTOS SAB ########
    }

    private function serialize(DiscountCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'discount_type' => $campaign->discount_type,
            'value' => D::represent(D::readRequired($campaign,'value')),
            'starts_at' => optional($campaign->starts_at)->format('Y-m-d H:i:s'),
            'expires_at' => optional($campaign->expires_at)->format('Y-m-d H:i:s'),
            'is_active' => (bool) $campaign->is_active,
            'is_valid' => $campaign->is_valid,
            'product_ids' => $campaign->relationLoaded('products') ? $campaign->products->pluck('id')->values()->all() : [],
            'category_ids' => $campaign->relationLoaded('categories') ? $campaign->categories->pluck('id')->values()->all() : [],
        ];
    }
}

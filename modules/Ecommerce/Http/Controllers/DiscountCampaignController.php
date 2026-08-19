<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Ecommerce\Models\Tenant\DiscountCampaign;
use App\Models\Tenant\Item;
use Modules\Item\Models\Category;
use Modules\Ecommerce\Services\CampaignPriceService;

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

        if ((float) $data['value'] > 100) {
            return response()->json([
                'message' => 'El porcentaje no puede ser mayor a 100%.',
                'errors' => ['value' => ['El porcentaje no puede ser mayor a 100%.']],
            ], 422);
        }

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
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:tenant.items,id'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:1'],
        ]);

        $items = Item::whereIn('id', collect($data['items'])->pluck('item_id'))->get()->keyBy('id');
        $pricingService = app(CampaignPriceService::class);

        $details = [];
        $totalDiscount = 0;
        $cartSubtotal = 0;

        foreach ($data['items'] as $line) {
            $item = $items->get($line['item_id']);
            $quantity = max(1, (float) ($line['quantity'] ?? 1));
            $pricing = $pricingService->forItem($item);
            $cartSubtotal += $pricing['base_price'] * $quantity;
            $discount = round($pricing['real_discount'] * $quantity, 2);
            $totalDiscount += $discount;
            $details[] = ['item_id' => $item->id, 'campaign_id' => $pricing['discount_campaign_id'], 'campaign' => $pricing['discount_campaign_name'], 'percentage' => $pricing['real_discount_percentage'], 'base_unit_price' => $pricing['base_price'], 'compare_at_price' => $pricing['compare_at_price'], 'final_unit_price' => $pricing['final_price'], 'discount' => $discount];
        }

        return ['success' => true, 'subtotal' => round($cartSubtotal, 2), 'discount' => round($totalDiscount, 2), 'total' => round(max(0, $cartSubtotal - $totalDiscount), 2), 'items' => $details];
    }

    private function serialize(DiscountCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'discount_type' => $campaign->discount_type,
            'value' => (float) $campaign->value,
            'starts_at' => optional($campaign->starts_at)->format('Y-m-d H:i:s'),
            'expires_at' => optional($campaign->expires_at)->format('Y-m-d H:i:s'),
            'is_active' => (bool) $campaign->is_active,
            'is_valid' => $campaign->is_valid,
            'product_ids' => $campaign->relationLoaded('products') ? $campaign->products->pluck('id')->values()->all() : [],
            'category_ids' => $campaign->relationLoaded('categories') ? $campaign->categories->pluck('id')->values()->all() : [],
        ];
    }
}

<?php

namespace Modules\Ecommerce\Services;

use App\Models\Tenant\EcommerceCampaign;
use App\Models\Tenant\Item;
use App\Support\Exact\Decimal as D;
use Illuminate\Support\Collection;
use Modules\Ecommerce\Models\Tenant\DiscountCampaign;

class CampaignPriceService
{
    private ?Collection $discountCampaigns = null;

    /** Server-owned price fractions for checkout; social-proof display prices are not discounts. */
    public function exactForItem(Item $item): array
    {
        $base = \App\Support\Exact\Decimal::readRequired($item, 'sale_unit_price');
        if ($base->isNegative()) {
            \App\Services\Fiscal\FiscalAmounts::error('items', 'El precio del producto no puede ser negativo.');
        }
        $campaign = $this->activeDiscountCampaigns()
            ->filter(fn (DiscountCampaign $candidate) => $candidate->appliesTo((int) $item->id, $item->category_id ? (int) $item->category_id : null))
            ->sort(function ($left, $right) {
                return \App\Support\Exact\Decimal::readRequired($right, 'value')
                    ->compareTo(\App\Support\Exact\Decimal::readRequired($left, 'value'));
            })->first();
        $percentage = $campaign ? \App\Support\Exact\Decimal::readRequired($campaign, 'value') : \App\Support\Exact\Decimal::of(0);
        if ($percentage->isNegative() || $percentage->isGreaterThan(100)) {
            \App\Services\Fiscal\FiscalAmounts::error('items', 'La campaña tiene un porcentaje inválido.');
        }
        $discount = $base->multipliedBy($percentage)->dividedBy(100);
        return [
            'base_price' => \App\Support\Exact\Decimal::fraction($base),
            'discount' => \App\Support\Exact\Decimal::fraction($discount),
            'final_price' => \App\Support\Exact\Decimal::fraction($base->minus($discount)),
            'percentage' => \App\Support\Exact\Decimal::fraction($percentage),
            'campaign_id' => $campaign?->id,
            'campaign_name' => $campaign?->name,
        ];
    }

    public function forItem(Item $item): array
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $exact = $this->exactForItem($item);
        $basePrice = D::of($exact['base_price']);
        $discount = D::of($exact['discount']);
        $finalPrice = D::of($exact['final_price']);
        $socialProof = EcommerceCampaign::current();
        $hasSocialProof = $socialProof !== null && $socialProof->hasActiveDiscount();
        $compareAtPrice = null;
        if ($hasSocialProof) {
            $value = D::readRequired($socialProof, 'discount_value');
            if ($value->isNegative()) {
                \App\Services\Fiscal\FiscalAmounts::error('items', 'El precio de referencia de la campaña no es válido.');
            }
            $anchor = $socialProof->discount_type === 'percentage'
                ? $basePrice->multipliedBy(D::of(1)->plus($value->dividedBy(100)))
                : $basePrice->plus($value);
            if ($anchor->isGreaterThan($basePrice)) $compareAtPrice = $anchor;
        }
        // Sin anclaje de Social Proof, una campaña real usa el precio base como
        // precio anterior tachado. Si Social Proof está activo, prevalece su
        // anclaje ficticio más alto y la campaña sigue descontando sobre la base.
        if (!$discount->isZero() && $compareAtPrice === null) {
            $compareAtPrice = $basePrice;
        }
        $values = ['base_price'=>$basePrice, 'final_price'=>$finalPrice, 'real_discount'=>$discount,
            'real_discount_percentage'=>D::of($exact['percentage'])];
        if ($compareAtPrice !== null) $values['compare_at_price'] = $compareAtPrice;
        return [
            'base_price' => D::represent($basePrice),
            'compare_at_price' => $compareAtPrice === null ? null : D::represent($compareAtPrice),
            'final_price' => D::represent($finalPrice),
            'real_discount' => D::represent($discount),
            'real_discount_percentage' => D::represent($exact['percentage']),
            'discount_campaign_id' => $exact['campaign_id'],
            'discount_campaign_name' => $exact['campaign_name'],
            'has_social_proof_price' => $hasSocialProof,
            'has_real_discount' => !$discount->isZero(),
            // Public prices only. Never copy the item's full snapshot (which includes costs).
            'calculation_snapshot' => D::snapshot($values),
        ];
        // ######## FIN CALCULOS EXACTOS PRO9 ########
    }

    private function activeDiscountCampaigns(): Collection
    {
        if ($this->discountCampaigns !== null) {
            return $this->discountCampaigns;
        }

        DiscountCampaign::deactivateExpired();

        return $this->discountCampaigns = DiscountCampaign::with(['products:id', 'categories:id'])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
    }
}

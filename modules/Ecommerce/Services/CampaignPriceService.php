<?php

namespace Modules\Ecommerce\Services;

use App\Models\Tenant\EcommerceCampaign;
use App\Models\Tenant\Item;
use Illuminate\Support\Collection;
use Modules\Ecommerce\Models\Tenant\DiscountCampaign;

class CampaignPriceService
{
    private ?Collection $discountCampaigns = null;

    public function forItem(Item $item): array
    {
        $basePrice = round((float) $item->sale_unit_price, 2);
        $socialProof = EcommerceCampaign::current();
        $compareAtPrice = $socialProof && $socialProof->hasActiveDiscount()
            ? $socialProof->compareAtPrice($basePrice)
            : null;

        $campaign = $this->activeDiscountCampaigns()
            ->filter(fn (DiscountCampaign $candidate) => $candidate->appliesTo((int) $item->id, $item->category_id ? (int) $item->category_id : null))
            ->sortByDesc('value')
            ->first();

        $discount = $campaign ? $campaign->calculateDiscount($basePrice) : 0;
        $finalPrice = round(max(0, $basePrice - $discount), 2);
        // Sin anclaje de Social Proof, una campaña real usa el precio base como
        // precio anterior tachado. Si Social Proof está activo, prevalece su
        // anclaje ficticio más alto y la campaña sigue descontando sobre la base.
        if ($campaign && $discount > 0 && $compareAtPrice === null) {
            $compareAtPrice = $basePrice;
        }

        return [
            'base_price' => $basePrice,
            'compare_at_price' => $compareAtPrice,
            'final_price' => $finalPrice,
            'real_discount' => $discount,
            'real_discount_percentage' => $campaign ? (float) $campaign->value : 0,
            'discount_campaign_id' => $campaign?->id,
            'discount_campaign_name' => $campaign?->name,
            'has_social_proof_price' => $socialProof !== null && $socialProof->hasActiveDiscount(),
            'has_real_discount' => $campaign !== null && $discount > 0,
        ];
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

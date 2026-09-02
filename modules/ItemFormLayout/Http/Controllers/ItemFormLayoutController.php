<?php

namespace Modules\ItemFormLayout\Http\Controllers;

use App\Models\Tenant\Configuration;
use Illuminate\Routing\Controller;
use Modules\ItemFormLayout\Models\ItemFormLayout;
use Modules\ItemFormLayout\Http\Requests\UpdateItemFormLayoutRequest;

class ItemFormLayoutController extends Controller
{
    public function show($variant)
    {
        if (! in_array($variant, ItemFormLayout::ALLOWED_VARIANTS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Variante no válida.',
            ], 422);
        }

        $record = ItemFormLayout::where('variant', $variant)->first();
        $pinnedFields = $record ? $record->pinned_fields : [];

        return [
            'success' => true,
            'data' => [
                'variant'       => $variant,
                'pinned_fields' => $this->filterPinnedFieldsByConfig($pinnedFields),
            ],
        ];
    }

    public function update(UpdateItemFormLayoutRequest $request, $variant)
    {
        $payload = $this->normalize($request->input('pinned_fields', []));
        $payload = $this->filterPinnedFieldsByConfig($payload);

        $record = ItemFormLayout::updateOrCreate(
            ['variant' => $variant],
            [
                'pinned_fields'      => $payload,
                'updated_by_user_id' => optional(auth()->user())->id,
            ]
        );

        return [
            'success' => true,
            'message' => 'Configuración guardada con éxito',
            'data' => [
                'variant'       => $record->variant,
                'pinned_fields' => $record->pinned_fields,
            ],
        ];
    }

    protected function filterPinnedFieldsByConfig(array $fields): array
    {
        if (! $this->isGlobalIgvHandlingEnabled()) {
            return $fields;
        }

        return array_values(array_filter($fields, function ($row) {
            return ($row['field_key'] ?? '') !== 'has_igv';
        }));
    }

    protected function isGlobalIgvHandlingEnabled(): bool
    {
        $configuration = Configuration::select('global_igv_handling')->first();

        if ($configuration && $configuration->global_igv_handling !== null) {
            return (bool) $configuration->global_igv_handling;
        }

        return true;
    }

    protected function normalize(array $fields): array
    {
        usort($fields, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return array_values(array_map(function ($row, $index) {
            return [
                'field_key' => $row['field_key'],
                'width'     => (int) $row['width'],
                'order'     => $index,
            ];
        }, $fields, array_keys($fields)));
    }
}

<?php

namespace Modules\Item\Imports;

use App\Models\Tenant\Item;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Item\Models\ProductVariable;
use Modules\Item\Models\ProductVariableValue;
use Modules\Item\Services\ItemVariationImportParser;
use Modules\Item\Services\ItemVariationService;

class ItemVariationsImport implements ToCollection
{
    use Importable;

    protected $data;

    public function collection(Collection $rows)
    {
        $parser = new ItemVariationImportParser();
        $service = new ItemVariationService();

        $total = max(count($rows) - 1, 0);
        $processed = 0;
        $created = 0;
        $skipped = 0;
        $errors = [];

        unset($rows[0]);

        foreach ($rows as $index => $row) {
            $excelRow = $index + 1;

            $isNullAll = $row->every(function ($el) {
                return is_null($el) || trim((string) $el) === '';
            });
            if ($isNullAll) {
                continue;
            }

            try {
                $internalId = trim((string) ($row[0] ?? ''));
                $variationsRaw = trim((string) ($row[1] ?? ''));

                if ($internalId === '') {
                    throw new Exception('El código interno es obligatorio');
                }
                if ($variationsRaw === '') {
                    throw new Exception('La columna Variaciones es obligatoria');
                }

                $parent = Item::where('internal_id', $internalId)->first();
                if (!$parent) {
                    throw new Exception("No existe un producto con código interno \"{$internalId}\"");
                }
                if (!is_null($parent->parent_item_id)) {
                    throw new Exception("\"{$internalId}\" es una variación; no puede tener variaciones propias");
                }
                if ($parent->is_set) {
                    throw new Exception("\"{$internalId}\" es un combo y no puede tener variaciones");
                }
                if (!(floatval($parent->sale_unit_price) > 0)) {
                    throw new Exception("\"{$internalId}\" debe tener precio unitario de venta mayor a 0");
                }

                $parsedVariables = $parser->parse($variationsRaw);
                $resolvedVariables = $this->resolveCatalog($parsedVariables);
                $rowsToCreate = $this->buildBulkRows($parent, $resolvedVariables);

                if (count($rowsToCreate) === 0) {
                    $skipped++;
                    $processed++;
                    continue;
                }

                $createdIds = $service->bulkCreate($parent, $rowsToCreate);
                $created += count($createdIds);
                $processed++;
            } catch (Exception $e) {
                $errors[] = 'Fila ' . $excelRow . ': ' . $e->getMessage();
            }
        }

        if (count($errors) > 0 && $created === 0 && $processed === 0) {
            throw new Exception(implode(' | ', array_slice($errors, 0, 5)));
        }

        $messageParts = [
            "Procesados: {$processed}",
            "Variaciones creadas: {$created}",
            "Omitidos (ya existían): {$skipped}",
        ];
        if (count($errors) > 0) {
            $messageParts[] = 'Errores: ' . count($errors);
        }

        $this->data = [
            'total' => $total,
            'processed' => $processed,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => implode(' · ', $messageParts)
                . (count($errors) ? ' · ' . implode(' | ', array_slice($errors, 0, 3)) : ''),
        ];
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $parsedVariables
     * @return array<int, array{variable: ProductVariable, values: Collection}>
     *
     * @throws Exception
     */
    protected function resolveCatalog(array $parsedVariables): array
    {
        $resolved = [];

        foreach ($parsedVariables as $variableData) {
            $variable = ProductVariable::firstOrCreate(
                ['name' => $variableData['name']],
                [
                    'value_type' => $variableData['value_type'],
                    'active' => true,
                ]
            );

            if ($variable->value_type !== $variableData['value_type']) {
                $expected = $variableData['value_type'] === ProductVariable::VALUE_TYPE_COLOR
                    ? 'Lista con color'
                    : 'Lista simple';
                $current = $variable->value_type === ProductVariable::VALUE_TYPE_COLOR
                    ? 'Lista con color'
                    : 'Lista simple';
                throw new Exception(
                    "La variable \"{$variable->name}\" ya existe como {$current}; el Excel pide {$expected}"
                );
            }

            if (!$variable->active) {
                $variable->active = true;
                $variable->save();
            }

            $position = (int) ProductVariableValue::where('product_variable_id', $variable->id)->max('position');
            $valueModels = collect();

            foreach ($variableData['values'] as $valueData) {
                $existing = ProductVariableValue::where('product_variable_id', $variable->id)
                    ->where('value', $valueData['value'])
                    ->first();

                if ($existing) {
                    if ($valueData['color'] && $existing->color !== $valueData['color']) {
                        $existing->color = $valueData['color'];
                    }
                    if (!$existing->active) {
                        $existing->active = true;
                    }
                    $existing->save();
                    $valueModels->push($existing);
                    continue;
                }

                $position++;
                $valueModels->push(ProductVariableValue::create([
                    'product_variable_id' => $variable->id,
                    'value' => $valueData['value'],
                    'color' => $valueData['color'],
                    'position' => $position,
                    'active' => true,
                ]));
            }

            $resolved[] = [
                'variable' => $variable,
                'values' => $valueModels,
            ];
        }

        return $resolved;
    }

    /**
     * @param Item $parent
     * @param array $resolvedVariables
     * @return array
     *
     * @throws Exception
     */
    protected function buildBulkRows(Item $parent, array $resolvedVariables): array
    {
        $combos = [[]];
        foreach ($resolvedVariables as $resolved) {
            $values = $resolved['values'];
            $combos = collect($combos)->flatMap(function ($combo) use ($values) {
                return $values->map(function ($value) use ($combo) {
                    return array_merge($combo, [$value]);
                });
            })->values()->all();
        }

        if (count($combos) > ItemVariationImportParser::MAX_COMBINATIONS) {
            throw new Exception(
                'Se superarían ' . ItemVariationImportParser::MAX_COMBINATIONS . ' combinaciones'
            );
        }

        $existingCombos = $parent->variations()
            ->with('variationValues')
            ->get()
            ->map(function ($variation) {
                return $variation->variationValues
                    ->pluck('product_variable_value_id')
                    ->sort()
                    ->values()
                    ->implode('-');
            });

        $rows = [];
        $usedInternalIds = Item::whereIn(
            'internal_id',
            collect($combos)->map(function ($combo) use ($parent) {
                return $this->buildInternalId($parent->internal_id, $combo);
            })->all()
        )->pluck('internal_id');

        foreach ($combos as $combo) {
            $valueIds = collect($combo)->pluck('id')->values();
            $comboKey = $valueIds->sort()->values()->implode('-');

            if ($existingCombos->contains($comboKey)) {
                continue;
            }

            $internalId = $this->buildInternalId($parent->internal_id, $combo);
            if ($usedInternalIds->contains($internalId)) {
                throw new Exception(
                    "El código interno sugerido \"{$internalId}\" ya existe. Ajusta valores o el código del padre."
                );
            }

            // Evitar colisión entre filas nuevas del mismo lote
            $usedInternalIds->push($internalId);

            $rows[] = [
                'internal_id' => $internalId,
                'barcode' => null,
                'sale_unit_price' => $parent->sale_unit_price,
                'stock' => 0,
                'variable_value_ids' => $valueIds->all(),
            ];
        }

        return $rows;
    }

    /**
     * @param string $parentInternalId
     * @param array<int, ProductVariableValue> $combo
     */
    protected function buildInternalId(string $parentInternalId, array $combo): string
    {
        $suffix = collect($combo)
            ->map(function ($value) {
                return $this->abbreviate($value->value);
            })
            ->implode('-');

        return Str::limit($parentInternalId . '-' . $suffix, 30, '');
    }

    protected function abbreviate(string $text): string
    {
        $clean = Str::ascii($text);
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $clean) ?? '';
        $clean = strtoupper($clean);

        return strlen($clean) <= 3 ? $clean : substr($clean, 0, 3);
    }
}

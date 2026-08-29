<?php

namespace Modules\Item\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Item\Models\ProductVariable;
use Modules\Item\Models\ProductVariableValue;

class ProductVariablesImport implements ToCollection
{
    use Importable;

    protected $data;

    public function collection(Collection $rows)
    {
        $parser = new ProductVariableCatalogParser();
        $parsed = $parser->parse($rows);

        $errors = $parsed['errors'];
        $variablesCreated = 0;
        $valuesCreated = 0;
        $valuesUpdated = 0;
        $valuesSkipped = 0;

        foreach ($parsed['groups'] as $group) {
            try {
                $result = DB::connection('tenant')->transaction(function () use ($group) {
                    return $this->persistGroup($group);
                });
                $variablesCreated += $result['variable_created'] ? 1 : 0;
                $valuesCreated += $result['created'];
                $valuesUpdated += $result['updated'];
                $valuesSkipped += $result['skipped'];
            } catch (Exception $e) {
                $errors[] = "Atributo \"{$group['name']}\": {$e->getMessage()}";
            }
        }

        if (count($errors) > 0 && $variablesCreated === 0 && $valuesCreated === 0 && $valuesUpdated === 0) {
            throw new Exception(implode(' | ', array_slice($errors, 0, 5)));
        }

        $messageParts = [
            "Filas leídas: {$parsed['total']}",
            "Atributos nuevos: {$variablesCreated}",
            "Valores creados: {$valuesCreated}",
            "Valores actualizados: {$valuesUpdated}",
            "Valores ya existentes: {$valuesSkipped}",
        ];
        if (count($errors) > 0) {
            $messageParts[] = 'Errores: ' . count($errors);
        }

        $this->data = [
            'total' => $parsed['total'],
            'variables_created' => $variablesCreated,
            'values_created' => $valuesCreated,
            'values_updated' => $valuesUpdated,
            'values_skipped' => $valuesSkipped,
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
     * @param array{name: string, value_type: string, values: array} $group
     * @return array{variable_created: bool, created: int, updated: int, skipped: int}
     *
     * @throws Exception
     */
    protected function persistGroup(array $group): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $variable = ProductVariable::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($group['name'])])
            ->first();

        $variableCreated = false;

        if ($variable) {
            if ($variable->value_type !== $group['value_type']) {
                $expected = $group['value_type'] === ProductVariable::VALUE_TYPE_COLOR
                    ? 'Lista con color'
                    : 'Lista simple';
                $current = $variable->value_type === ProductVariable::VALUE_TYPE_COLOR
                    ? 'Lista con color'
                    : 'Lista simple';
                throw new Exception(
                    "ya existe como {$current}; el Excel pide {$expected}"
                );
            }
        } else {
            $variable = ProductVariable::create([
                'name' => $group['name'],
                'value_type' => $group['value_type'],
                'active' => true,
            ]);
            $variableCreated = true;
        }

        $maxPosition = (int) ProductVariableValue::where('product_variable_id', $variable->id)->max('position');
        $existingValues = ProductVariableValue::where('product_variable_id', $variable->id)->get();

        foreach ($group['values'] as $valueData) {
            $lookup = mb_strtolower($valueData['value']);
            $existing = $existingValues->first(function ($row) use ($lookup) {
                return mb_strtolower($row->value) === $lookup;
            });

            if ($existing) {
                $dirty = false;
                if ($valueData['color'] && $existing->color !== $valueData['color']) {
                    $existing->color = $valueData['color'];
                    $dirty = true;
                }
                if ($existing->active !== $valueData['active']) {
                    $existing->active = $valueData['active'];
                    $dirty = true;
                }
                if ($dirty) {
                    $existing->save();
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            $position = $valueData['position'];
            if ($position <= $maxPosition) {
                $maxPosition++;
                $position = $maxPosition;
            } else {
                $maxPosition = $position;
            }

            $createdValue = ProductVariableValue::create([
                'product_variable_id' => $variable->id,
                'value' => $valueData['value'],
                'color' => $group['value_type'] === ProductVariable::VALUE_TYPE_COLOR
                    ? $valueData['color']
                    : null,
                'position' => $position,
                'active' => $valueData['active'],
            ]);
            $existingValues->push($createdValue);
            $created++;
        }

        return [
            'variable_created' => $variableCreated,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}

<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Class DocumentRequest
 *
 * @package App\Http\Requests\Tenant
 * @mixin FormRequest
 */
class DocumentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'customer_id' => [
                'required',
            ],
            'establishment_id' => [
                'required',
            ],
            'series' => [
                'required',
            ],
            'date_of_issue' => [
                'required',
            ],
            'note.note_credit_type_id' => [
                'required_if:document_type_id, "07"',
            ],
            'note.note_debit_type_id' => [
                'required_if:document_type_id, "08"',
            ],
            'note.note_description' => [
                'required_if:document_type_id,"07", "08"',
            ],
            'exchange_rate_sale' => [
                'required',
                'numeric',
                'min:0.01'
            ],
        ];
    }

    /**
     * Validación de lotes: productos con lots_enabled deben traer IdLoteSelected
     * con cantidades que sumen exactamente la cantidad del ítem.
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function (Validator $validator) {
            $this->validatePenaltyDebitNote($validator);
        });

        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);

            if (!is_array($items) || empty($items)) {
                return;
            }

            foreach ($items as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (!$this->itemRequiresLotSelection($row)) {
                    continue;
                }

                $description = $this->resolveItemDescription($row, $index);
                $quantity = round((float) ($row['quantity'] ?? 0), 4);
                $idLoteSelected = $row['IdLoteSelected']
                    ?? data_get($row, 'item.IdLoteSelected')
                    ?? null;

                if ($this->isEmptyLotSelection($idLoteSelected)) {
                    $validator->errors()->add(
                        "items.{$index}.IdLoteSelected",
                        "Debe seleccionar lote para el producto \"{$description}\"."
                    );
                    continue;
                }

                // Formato legacy: un solo id de lote cubre toda la cantidad del ítem
                if (!is_array($idLoteSelected)) {
                    continue;
                }

                $sumCompromise = 0.0;
                foreach ($idLoteSelected as $lotRow) {
                    $lotId = $this->lotField($lotRow, 'id');
                    $compromise = (float) $this->lotField($lotRow, 'compromise_quantity', 0);

                    if ($lotId === null || $lotId === '') {
                        $validator->errors()->add(
                            "items.{$index}.IdLoteSelected",
                            "Lote inválido o sin identificador para el producto \"{$description}\"."
                        );
                        continue 2;
                    }

                    if ($compromise <= 0) {
                        $validator->errors()->add(
                            "items.{$index}.IdLoteSelected",
                            "El lote #{$lotId} del producto \"{$description}\" debe tener cantidad comprometida mayor a 0."
                        );
                        continue 2;
                    }

                    $sumCompromise += $compromise;
                }

                $sumCompromise = round($sumCompromise, 4);

                if ($sumCompromise !== $quantity) {
                    $validator->errors()->add(
                        "items.{$index}.IdLoteSelected",
                        "Las cantidades de lote del producto \"{$description}\" ({$sumCompromise}) deben coincidir con la cantidad vendida ({$quantity})."
                    );
                }
            }
        });
    }

    /**
     * Las notas de débito por penalidad (motivo 13) son operaciones inafectas
     * al IGV, por lo que se rechazan si llegan con IGV.
     */
    private function validatePenaltyDebitNote(Validator $validator): void
    {
        if ($this->input('document_type_id') !== '08') {
            return;
        }

        // el formulario envía note_credit_or_debit_type_id, otros orígenes envían el nodo note
        $noteDebitTypeId = $this->input('note.note_debit_type_id')
            ?? $this->input('note_credit_or_debit_type_id');

        if ((string) $noteDebitTypeId !== '13') {
            return;
        }

        $hasIgv = round((float) $this->input('total_igv', 0), 2) > 0;

        if (!$hasIgv) {
            foreach ($this->input('items', []) as $row) {
                if (is_array($row) && round((float) ($row['total_igv'] ?? 0), 2) > 0) {
                    $hasIgv = true;
                    break;
                }
            }
        }

        if ($hasIgv) {
            $validator->errors()->add(
                'note.note_debit_type_id',
                'Las penalidades son operaciones inafectas del IGV'
            );
        }
    }

    /**
     * Determina si el ítem exige selección de lote (payload o modelo Item).
     */
    private function itemRequiresLotSelection(array $row): bool
    {
        $fromPayload = filter_var(
            data_get($row, 'item.lots_enabled', data_get($row, 'lots_enabled', false)),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($fromPayload) {
            return true;
        }

        $itemId = $row['item_id'] ?? data_get($row, 'item.id');
        if (!$itemId) {
            return false;
        }

        return (bool) Item::query()->where('id', $itemId)->value('lots_enabled');
    }

    private function resolveItemDescription(array $row, $index): string
    {
        $description = data_get($row, 'item.description')
            ?? data_get($row, 'description')
            ?? null;

        if ($description) {
            return (string) $description;
        }

        return 'ítem #' . ((int) $index + 1);
    }

    /**
     * @param mixed $idLoteSelected
     */
    private function isEmptyLotSelection($idLoteSelected): bool
    {
        if ($idLoteSelected === null || $idLoteSelected === '' || $idLoteSelected === false) {
            return true;
        }

        if (is_array($idLoteSelected) && count($idLoteSelected) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Lee un campo de lote ya sea array asociativo o objeto.
     *
     * @param mixed $lotRow
     * @param mixed $default
     * @return mixed
     */
    private function lotField($lotRow, string $key, $default = null)
    {
        if (is_array($lotRow)) {
            return $lotRow[$key] ?? $default;
        }

        if (is_object($lotRow)) {
            return $lotRow->{$key} ?? $default;
        }

        return $default;
    }
}
